<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\Item;
use App\Models\ItemTemplate;
use App\Models\MailMessage;
use App\Models\Resources;
use App\Models\Slot;
use App\Models\Storage;
use App\Models\TemporarySlot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MailService
{
    public const OUTBOX_SLOT_COUNT = 6;
    public const MAIL_TTL_DAYS = 30;

    public function __construct(
        private StorageProvisioningService $provisioningService,
        private InventoryService $inventoryService,
        private StorageMoveService $moveService,
        private SlotCellResolver $slotCellResolver,
        private EventStore $eventStore,
    ) {}

    public function ensurePostCharacter(): Character
    {
        return Character::firstOrCreate(
            ['character_type' => 'post', 'name' => 'Почтовая служба'],
            ['active' => true],
        );
    }

    public function ensureInboxStorage(): Storage
    {
        $post = $this->ensurePostCharacter();

        return Storage::firstOrCreate(
            [
                'characters_uuid' => $post->uuid,
                'storage_type' => 'post_inbox',
            ],
            ['name' => 'Входящие', 'active' => true],
        );
    }

    /** Исходящий ящик на любом персонаже — создаётся при первой попытке написать письмо. */
    public function ensureOutboxStorage(Character $character): Storage
    {
        return $this->provisioningService->grantStorage($character, 'post_outbox');
    }

    public function sendMail(
        Character $sender,
        Character $recipient,
        string $subject,
        string $body,
    ): MailMessage {
        if ($sender->uuid === $recipient->uuid) {
            throw new \RuntimeException('Нельзя отправить письмо самому себе');
        }

        return DB::transaction(function () use ($sender, $recipient, $subject, $body) {
            $occupants = $this->collectOutboxOccupants($sender);
            $attachmentCount = $occupants->count();

            if ($attachmentCount > self::OUTBOX_SLOT_COUNT) {
                throw new \RuntimeException('Слишком много вложений');
            }

            $message = $this->createMessageRecord(
                $recipient,
                $sender,
                $sender->name,
                $subject,
                $body,
                $attachmentCount,
            );

            if ($attachmentCount > 0) {
                $this->deliverOccupantsToInbox($recipient, $message, $occupants);
            }

            $this->notifyMailSent($message, $sender, $recipient);

            return $message->fresh();
        });
    }

    /**
     * @param  array<int, Item|Resources|array{template_slug: string, quantity: int}>  $attachments
     */
    public function sendSystemMail(
        Character $recipient,
        string $subject,
        string $body,
        array $attachments = [],
        ?string $senderName = 'Система',
    ): MailMessage {
        return DB::transaction(function () use ($recipient, $subject, $body, $attachments, $senderName) {
            $items = collect($attachments)->filter(fn ($a) => $a instanceof Item || $a instanceof Resources);
            $attachmentCount = min($items->count(), self::OUTBOX_SLOT_COUNT);

            $message = $this->createMessageRecord(
                $recipient,
                null,
                $senderName ?? 'Система',
                $subject,
                $body,
                $attachmentCount,
            );

            $slotIndex = 0;
            foreach ($attachments as $attachment) {
                if ($slotIndex >= self::OUTBOX_SLOT_COUNT) {
                    break;
                }

                if ($attachment instanceof Item || $attachment instanceof Resources) {
                    $tempSlot = $this->createInboxTempSlot($recipient, $message, $slotIndex);
                    $attachment->update([
                        'slot_uuid' => $tempSlot->uuid,
                        'buffer_slot_uuid' => null,
                    ]);
                    $slotIndex++;
                } elseif (is_array($attachment)) {
                    $templateSlug = $attachment['template_slug'] ?? null;
                    $quantity = (int) ($attachment['quantity'] ?? 1);
                    if (!$templateSlug) {
                        continue;
                    }
                    $tempSlot = $this->createInboxTempSlot($recipient, $message, $slotIndex);
                    $this->depositResourceToInboxSlot($tempSlot, $templateSlug, $quantity);
                    $slotIndex++;
                }
            }

            $message->update(['attachment_count' => $slotIndex]);
            $this->notifyMailSent($message, null, $recipient);

            return $message->fresh();
        });
    }

    public function getInbox(Character $recipient): Collection
    {
        return MailMessage::query()
            ->where('recipient_uuid', $recipient->uuid)
            ->whereNotIn('status', ['deleted'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (MailMessage $message) => $this->formatInboxEntry($message));
    }

    public function getUnreadCount(Character $recipient): int
    {
        return MailMessage::query()
            ->where('recipient_uuid', $recipient->uuid)
            ->where('status', 'unread')
            ->count();
    }

    public function getMessageForRecipient(Character $recipient, string $messageUuid): MailMessage
    {
        return MailMessage::where('uuid', $messageUuid)
            ->where('recipient_uuid', $recipient->uuid)
            ->whereNotIn('status', ['deleted'])
            ->firstOrFail();
    }

    public function getInboxTempSlots(Character $recipient, string $messageUuid): Collection
    {
        $this->getMessageForRecipient($recipient, $messageUuid);

        return TemporarySlot::query()
            ->where('mail_message_uuid', $messageUuid)
            ->where('character_uuid', $recipient->uuid)
            ->where('active', true)
            ->orderBy('slot_index')
            ->get();
    }

    public function markRead(Character $recipient, string $messageUuid): MailMessage
    {
        $message = $this->getMessageForRecipient($recipient, $messageUuid);

        if ($message->status === 'unread') {
            $message->update([
                'status' => 'read',
                'read_at' => now(),
            ]);
        }

        return $message->fresh();
    }

    public function claimAll(Character $recipient, string $messageUuid): MailMessage
    {
        return DB::transaction(function () use ($recipient, $messageUuid) {
            $message = $this->getMessageForRecipient($recipient, $messageUuid);
            $inventory = $recipient->storages()->where('storage_type', 'inventory')->firstOrFail();

            foreach ($this->getInboxTempSlots($recipient, $messageUuid) as $tempSlot) {
                $occupant = $this->slotCellResolver->getOccupantForTemporarySlot($tempSlot);
                if (!$occupant) {
                    continue;
                }

                $slotType = $occupant instanceof Item
                    ? ($occupant->slot_type ?? $occupant->template?->slot_type)
                    : ($occupant->slot_type ?? $occupant->template?->slot_type);

                $freeSlot = $this->inventoryService->findFreeSlot($inventory, $slotType);
                if (!$freeSlot) {
                    continue;
                }

                $this->moveService->move($recipient, $tempSlot->uuid, $freeSlot->uuid);
                $tempSlot->update(['active' => false]);
            }

            return $this->markClaimedIfEmpty($message->fresh());
        });
    }

    public function deleteMessage(Character $recipient, string $messageUuid): void
    {
        DB::transaction(function () use ($recipient, $messageUuid) {
            $message = $this->getMessageForRecipient($recipient, $messageUuid);

            if ($this->messageHasAttachments($message)) {
                throw new \RuntimeException('Сначала заберите вложения');
            }

            $message->update(['status' => 'deleted']);
            $this->deactivateInboxSlots($message);
        });
    }

    public function findMessageByInboxSlot(TemporarySlot $tempSlot): ?MailMessage
    {
        if (!$tempSlot->mail_message_uuid) {
            return null;
        }

        return MailMessage::where('uuid', $tempSlot->mail_message_uuid)->first();
    }

    public function assertRecipientOwnsInboxSlot(Character $recipient, TemporarySlot $tempSlot): MailMessage
    {
        if ($tempSlot->character_uuid !== $recipient->uuid) {
            throw new \RuntimeException('Нет доступа к этому письму');
        }

        $message = $this->findMessageByInboxSlot($tempSlot);
        if (!$message || $message->recipient_uuid !== $recipient->uuid) {
            throw new \RuntimeException('Нет доступа к этому письму');
        }

        if (in_array($message->status, ['claimed', 'deleted'], true)) {
            throw new \RuntimeException('Письмо уже обработано');
        }

        return $message;
    }

    public function markClaimedIfEmpty(MailMessage $message): MailMessage
    {
        if (!$this->messageHasAttachments($message)) {
            $message->update([
                'status' => 'claimed',
                'claimed_at' => now(),
            ]);
        }

        return $message->fresh();
    }

    public function purgeExpired(): int
    {
        $messages = MailMessage::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->whereNotIn('status', ['deleted'])
            ->get();

        $count = 0;
        foreach ($messages as $message) {
            if ($this->messageHasAttachments($message)) {
                continue;
            }
            $message->update(['status' => 'deleted']);
            $this->deactivateInboxSlots($message);
            $count++;
        }

        return $count;
    }

    public function messageHasAttachments(MailMessage $message): bool
    {
        return TemporarySlot::query()
            ->where('mail_message_uuid', $message->uuid)
            ->where('active', true)
            ->get()
            ->contains(fn (TemporarySlot $slot) => $this->slotCellResolver->getOccupantForTemporarySlot($slot) !== null);
    }

    private function createMessageRecord(
        Character $recipient,
        ?Character $sender,
        string $senderName,
        string $subject,
        string $body,
        int $attachmentCount,
    ): MailMessage {
        $inboxStorage = $attachmentCount > 0 ? $this->ensureInboxStorage() : null;

        return MailMessage::create([
            'storage_uuid' => $inboxStorage?->uuid,
            'recipient_uuid' => $recipient->uuid,
            'sender_uuid' => $sender?->uuid,
            'sender_name' => $senderName,
            'subject' => $subject,
            'body' => $body,
            'attachment_count' => $attachmentCount,
            'status' => 'unread',
            'expires_at' => now()->addDays(self::MAIL_TTL_DAYS),
        ]);
    }

    /**
     * @return Collection<int, Item|Resources>
     */
    private function collectOutboxOccupants(Character $sender): Collection
    {
        $outbox = $this->ensureOutboxStorage($sender);
        $occupants = collect();

        foreach ($outbox->slots()->orderBy('id')->get() as $slot) {
            $occupant = $this->slotCellResolver->getOccupantForRegularSlot($slot);
            if ($occupant) {
                $occupants->push($occupant);
            }
        }

        return $occupants;
    }

    /**
     * @param  Collection<int, Item|Resources>  $occupants
     */
    private function deliverOccupantsToInbox(Character $recipient, MailMessage $message, Collection $occupants): void
    {
        foreach ($occupants->values() as $index => $occupant) {
            $tempSlot = $this->createInboxTempSlot($recipient, $message, $index);
            $occupant->update([
                'slot_uuid' => $tempSlot->uuid,
                'buffer_slot_uuid' => null,
            ]);
        }
    }

    private function createInboxTempSlot(Character $recipient, MailMessage $message, int $slotIndex): TemporarySlot
    {
        $inboxStorage = $this->ensureInboxStorage();

        return TemporarySlot::create([
            'uuid' => Str::uuid()->toString(),
            'storage_uuid' => $inboxStorage->uuid,
            'character_uuid' => $recipient->uuid,
            'mail_message_uuid' => $message->uuid,
            'slot_index' => $slotIndex,
            'active' => true,
            'timestamps_end' => $message->expires_at,
        ]);
    }

    private function depositResourceToInboxSlot(TemporarySlot $tempSlot, string $templateSlug, int $quantity): void
    {
        $template = ItemTemplate::where('slug', $templateSlug)->firstOrFail();

        Resources::create([
            'uuid' => Str::uuid()->toString(),
            'slot_uuid' => $tempSlot->uuid,
            'recipe_slug' => $templateSlug,
            'template_slug' => $templateSlug,
            'slot_type' => $template->slot_type ?? $templateSlug,
            'quantity' => $quantity,
            'max_stack' => $template->max_stack ?? $quantity,
        ]);
    }

    private function deactivateInboxSlots(MailMessage $message): void
    {
        TemporarySlot::where('mail_message_uuid', $message->uuid)->update(['active' => false]);
    }

    private function notifyMailSent(MailMessage $message, ?Character $sender, Character $recipient): void
    {
        $correlationUuid = Str::uuid()->toString();
        $payload = [
            'message_uuid' => $message->uuid,
            'sender_name' => $message->sender_name,
            'subject' => $message->subject,
            'has_attachments' => $message->attachment_count > 0,
        ];

        $this->eventStore->record(
            'mail.received',
            'mail_message',
            $message->uuid,
            $payload,
            $recipient->uuid,
            $correlationUuid,
        );

        if ($sender) {
            $this->eventStore->record(
                'mail.sent',
                'mail_message',
                $message->uuid,
                $payload,
                $sender->uuid,
                $correlationUuid,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formatInboxEntry(MailMessage $message): array
    {
        return [
            'uuid' => $message->uuid,
            'sender_uuid' => $message->sender_uuid,
            'sender_name' => $message->sender_name,
            'subject' => $message->subject,
            'body' => $message->body,
            'status' => $message->status,
            'has_attachments' => $message->attachment_count > 0 && $this->messageHasAttachments($message),
            'attachment_count' => $message->attachment_count,
            'read_at' => $message->read_at?->toIso8601String(),
            'claimed_at' => $message->claimed_at?->toIso8601String(),
            'created_at' => $message->created_at?->toIso8601String(),
            'expires_at' => $message->expires_at?->toIso8601String(),
        ];
    }
}
