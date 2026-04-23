@once
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
/**
 * Alpine-Factory fuer sortierbare Livewire-Listen.
 * Einbinden auf dem Container (z.B. tbody):
 *   x-data="sortableList('reorderBookings')"
 * Die Kinder tragen [data-sortable-uuid] und eine Zelle mit .js-drag-handle.
 * Beim Drop wird $wire[actionName](uuids[]) gerufen.
 */
window.sortableList = function (actionName) {
    return {
        _instance: null,

        init() {
            const self = this;
            const el = this.$el;

            const attach = function (attempts) {
                attempts = attempts == null ? 200 : attempts;
                if (!window.Sortable) {
                    if (attempts <= 0) {
                        console.warn('[sortableList] SortableJS nicht geladen – Drag-&-Drop deaktiviert');
                        return;
                    }
                    return setTimeout(function () { attach(attempts - 1); }, 50);
                }
                if (self._instance) return;
                self._instance = window.Sortable.create(el, {
                    animation: 150,
                    handle: '.js-drag-handle',
                    filter: 'input,textarea,select,button,a',   // solche Felder nicht versehentlich greifen
                    preventOnFilter: false,
                    ghostClass: 'opacity-50',
                    chosenClass: 'ring-2',
                    onEnd: function (evt) {
                        if (evt.oldIndex === evt.newIndex) return;
                        const uuids = Array.from(el.querySelectorAll('[data-sortable-uuid]'))
                            .map(function (e) { return e.dataset.sortableUuid; });
                        if (self.$wire && typeof self.$wire.call === 'function') {
                            self.$wire.call(actionName, uuids);
                        }
                    },
                });
            };
            attach();

            // Teardown bei Alpine-Destroy
            return function () {
                if (self._instance) {
                    try { self._instance.destroy(); } catch (e) {}
                    self._instance = null;
                }
            };
        },
    };
};
</script>
@endonce
