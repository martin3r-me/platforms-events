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
                console.log('[sortableList] attach', { action: actionName, el: el, rows: el.querySelectorAll('[data-sortable-uuid]').length });
                self._instance = window.Sortable.create(el, {
                    animation: 150,
                    // handle vorerst deaktiviert zum Debuggen – wenn ohne handle
                    // drag funktioniert, liegt's am Handle-Selector.
                    // handle: '.js-drag-handle',
                    filter: 'input,textarea,select,button,a',
                    preventOnFilter: false,
                    ghostClass: 'opacity-50',
                    chosenClass: 'ring-2',
                    onChoose: function (evt) {
                        console.log('[sortableList] onChoose', { action: actionName, item: evt.item, target: evt.originalEvent?.target });
                    },
                    onStart: function (evt) {
                        console.log('[sortableList] onStart', { action: actionName, oldIndex: evt.oldIndex });
                    },
                    onEnd: function (evt) {
                        console.log('[sortableList] onEnd', { action: actionName, oldIndex: evt.oldIndex, newIndex: evt.newIndex });
                        if (evt.oldIndex === evt.newIndex) return;
                        const uuids = Array.from(el.querySelectorAll('[data-sortable-uuid]'))
                            .map(function (e) { return e.dataset.sortableUuid; });
                        console.log('[sortableList] calling', actionName, uuids);
                        if (self.$wire && typeof self.$wire.call === 'function') {
                            self.$wire.call(actionName, uuids);
                        } else {
                            console.warn('[sortableList] $wire.call nicht verfuegbar');
                        }
                    },
                });
                console.log('[sortableList] Sortable-Instanz erstellt');
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
