@extends('layouts.game')

@section('title', 'Крафт-Мир')

@section('center')
    <div id="tool-workbench" class="tool-panel" style="display:block">
        @include('partials.workbench')
    </div>
    <div id="tool-auction" class="tool-panel" style="display:none">
        @include('partials.auction')
    </div>
    <div id="tool-trade" class="tool-panel" style="display:none">
        <div style="text-align:center;padding:40px;color:#666">
            <h2 style="color:#d4a574;margin-bottom:20px">🤝 Обмен</h2>
            <p>Функция обмена между игроками в разработке</p>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    // Обработчик двойного клика на предметах инвентаря
    document.addEventListener('DOMContentLoaded', () => {
        const inventoryContent = document.getElementById('inventoryContent');
        
        inventoryContent.addEventListener('dblclick', (e) => {
            const itemEl = e.target.closest('.item');
            if (!itemEl) return;

            const uuid = itemEl.dataset.uuid;
            const item = GameState.inventory.find(i => i.uuid === uuid);
            if (!item) return;

            // Убеждаемся что все нужные поля есть
            const fullItem = {
                ...item,
                uuid: itemEl.dataset.uuid,
                name: itemEl.dataset.name,
                stage: itemEl.dataset.stage || item.stage,
                recipe_slug: itemEl.dataset.recipeSlug || item.recipe_slug,
                template_slug: itemEl.dataset.templateSlug || item.template_slug,
            };

            // Определяем текущий инструмент
            const currentTool = document.querySelector('.tool-btn.active')?.dataset.tool || 'workbench';

            if (currentTool === 'workbench' && typeof handleWorkbenchDrop === 'function') {
                handleWorkbenchDrop(fullItem);
            } else if (currentTool === 'auction' && typeof handleAuctionDrop === 'function') {
                handleAuctionDrop(fullItem);
            }
        });
    });

    // Инициализация инструментов
    function initTools() {
        if (typeof initWorkbench === 'function') initWorkbench();
        if (typeof initAuction === 'function') initAuction();
    }

    // Запускаем инициализацию после загрузки данных
    const originalLoadPlayerData = window.loadPlayerData;
    window.loadPlayerData = async function() {
        await originalLoadPlayerData();
        initTools();
    };
</script>
@endpush
