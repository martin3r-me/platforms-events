{{-- @assets statt @once: Livewire 3 injiziert den Block einmalig in <head> und
     haelt ihn ueber wire:navigate-Wechsel persistent. @once wuerde das Script
     bei SPA-Navigation aus dem DOM entfernen und Sortable laeuft danach nicht. --}}
@assets
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<style>
.events-sortable-ghost {
    opacity: 0.35 !important;
    background: #f3f4f6 !important;
}
.events-sortable-fallback {
    opacity: 0.95 !important;
    background: #ffffff !important;
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.18);
    outline: 1px solid rgba(0, 0, 0, 0.08);
    pointer-events: none;
}
</style>
<script>
/**
 * Sortable-Init ohne Alpine: alle Container mit [data-sortable-action]
 * werden gescannt und initialisiert. Re-Init bei Livewire-Morph via
 * Livewire-Hook.
 *
 * Benutzung:
 *   <tbody data-sortable-action="reorderBookings">
 *       <tr data-sortable-uuid="...">...</tr>
 *   </tbody>
 */
(function () {
    if (window._eventsSortableBooted) return;
    window._eventsSortableBooted = true;

    const KEY = '__eventsSortableInstance';

    function initOne(el) {
        if (!window.Sortable) return false;
        if (el[KEY]) return true;

        const action = el.getAttribute('data-sortable-action');
        if (!action) return false;

        el[KEY] = window.Sortable.create(el, {
            animation: 150,
            handle: '.js-drag-handle',
            filter: 'input,textarea,select,button,a',
            preventOnFilter: false,
            forceFallback: true,
            fallbackOnBody: true,
            fallbackTolerance: 3,
            ghostClass: 'events-sortable-ghost',
            chosenClass: 'ring-2',
            fallbackClass: 'events-sortable-fallback',
            onEnd: function (evt) {
                if (evt.oldIndex === evt.newIndex) return;
                const uuids = Array.from(el.querySelectorAll('[data-sortable-uuid]'))
                    .map(function (n) { return n.dataset.sortableUuid; });

                // Naechsten Livewire-Component nach oben finden und Action rufen.
                const wireEl = el.closest('[wire\\:id]');
                if (!wireEl || !window.Livewire) return;
                const component = window.Livewire.find(wireEl.getAttribute('wire:id'));
                if (component && typeof component.call === 'function') {
                    component.call(action, uuids);
                }
            },
        });
        return true;
    }

    function initAll(root) {
        const scope = root || document;
        const nodes = scope.querySelectorAll('[data-sortable-action]');
        nodes.forEach(initOne);
    }

    // SortableJS kann asynchron laden. Retry, bis verfuegbar.
    (function waitForSortable(attempts) {
        if (window.Sortable) return initAll();
        if (attempts <= 0) return console.warn('[eventsSortable] SortableJS nicht geladen');
        setTimeout(function () { waitForSortable(attempts - 1); }, 50);
    })(200);

    // Initial + bei Livewire-Updates
    document.addEventListener('DOMContentLoaded', function () { initAll(); });
    document.addEventListener('livewire:navigated', function () { initAll(); });
    document.addEventListener('livewire:initialized', function () {
        initAll();
        if (window.Livewire && typeof window.Livewire.hook === 'function') {
            window.Livewire.hook('morph.updated', function () {
                // Nach Morph koennen neue sortable-Container aufgetaucht sein.
                initAll();
            });
        }
    });
})();
</script>
@endassets
