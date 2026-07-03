<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Character;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\MailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\WorkbenchHelper;
use Tests\TestCase;

class MailServiceTest extends TestCase
{
    use RefreshDatabase;

    private MailService $mailService;
    private InventoryService $inventoryService;
    private Character $sender;
    private Character $recipient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->mailService = app(MailService::class);
        $this->inventoryService = app(InventoryService::class);

        $senderUser = User::where('email', 'test@example.com')->first();
        $this->sender = $senderUser->characters()->where('character_type', 'player')->first();

        $recipientUser = User::create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Recipient',
            'email' => 'recipient@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->recipient = Character::create([
            'uuid' => Str::uuid()->toString(),
            'user_uuid' => $recipientUser->uuid,
            'character_type' => 'player',
            'name' => 'Recipient Character',
            'active' => true,
        ]);

        $inventory = \App\Models\Storage::create([
            'uuid' => Str::uuid()->toString(),
            'characters_uuid' => $this->recipient->uuid,
            'storage_type' => 'inventory',
            'name' => 'Инвентарь',
            'active' => true,
        ]);
        for ($i = 0; $i < 36; $i++) {
            \App\Models\Slot::create([
                'uuid' => Str::uuid()->toString(),
                'storage_uuid' => $inventory->uuid,
                'slot_type' => null,
            ]);
        }
    }

    public function test_send_mail_with_outbox_attachment(): void
    {
        $this->inventoryService->addResource($this->sender, 'wood', 10);
        $item = WorkbenchHelper::craftWoodenSwordFromInventory($this->sender);

        $outbox = $this->mailService->ensureOutboxStorage($this->sender);
        $outboxSlot = $outbox->slots()->firstOrFail();
        $item->update(['slot_uuid' => $outboxSlot->uuid]);

        $message = $this->mailService->sendMail(
            $this->sender,
            $this->recipient,
            'Тест',
            'Тело письма',
        );

        $this->assertEquals('unread', $message->status);
        $this->assertEquals(1, $message->attachment_count);
        $this->assertCount(1, $this->mailService->getInboxTempSlots($this->recipient, $message->uuid));

        $inbox = $this->mailService->getInbox($this->recipient);
        $this->assertCount(1, $inbox);
        $this->assertTrue($inbox->first()['has_attachments']);
    }

    public function test_send_mail_without_attachments_creates_no_inbox_slots(): void
    {
        $message = $this->mailService->sendMail(
            $this->sender,
            $this->recipient,
            'Только текст',
            'Привет',
        );

        $this->assertEquals(0, $message->attachment_count);
        $this->assertCount(0, $this->mailService->getInboxTempSlots($this->recipient, $message->uuid));
    }

    public function test_claim_all_moves_items_to_recipient_inventory(): void
    {
        $this->inventoryService->addResource($this->sender, 'wood', 10);
        $item = WorkbenchHelper::craftWoodenSwordFromInventory($this->sender);
        $outbox = $this->mailService->ensureOutboxStorage($this->sender);
        $item->update(['slot_uuid' => $outbox->slots()->firstOrFail()->uuid]);

        $message = $this->mailService->sendMail($this->sender, $this->recipient, 'Лут', '');

        $claimed = $this->mailService->claimAll($this->recipient, $message->uuid);

        $this->assertEquals('claimed', $claimed->status);
        $this->assertGreaterThan(
            0,
            $this->inventoryService->getCharacterItems($this->recipient)->where('uuid', $item->uuid)->count()
        );
    }

    public function test_system_mail_for_resources(): void
    {
        $message = $this->mailService->sendSystemMail(
            $this->recipient,
            'Награда',
            'Дерево',
            [['template_slug' => 'wood', 'quantity' => 5]],
        );

        $this->assertEquals('unread', $message->status);
        $this->assertEquals(1, $message->attachment_count);
        $claimed = $this->mailService->claimAll($this->recipient, $message->uuid);
        $this->assertEquals('claimed', $claimed->status);
        $this->assertGreaterThanOrEqual(5, $this->inventoryService->getResourceQuantity($this->recipient, 'wood'));
    }

    public function test_outbox_created_on_first_compose_attempt(): void
    {
        $this->assertNull($this->sender->storages()->where('storage_type', 'post_outbox')->first());
        $outbox = $this->mailService->ensureOutboxStorage($this->sender);
        $this->assertNotNull($outbox);
        $this->assertEquals(6, $outbox->slots()->count());
    }
}
