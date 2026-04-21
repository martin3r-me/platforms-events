@props([
    'wireProperty' => '',   {{-- z.B. 'contractText' oder 'tplForm.html_content' --}}
    'initial'      => '',
    'height'       => 600,
    'uniqueId'     => null,
])

@php
    $uid = $uniqueId ?: ('tiny-' . uniqid());
@endphp

{{-- Script-Definition VOR dem div, damit window.tinymceEditor verfuegbar ist,
     wenn Alpine das x-data-Attribut evaluiert. --}}
@once
<script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js" referrerpolicy="origin"></script>
<script>
window.tinymceEditor = function (opts) {
    return {
        uid: opts.uid,
        wireProperty: opts.wireProperty,
        initial: opts.initial || '',
        height: opts.height || 600,
        _editor: null,
        _wireId: null,

        init() {
            const rootEl = this.$root;
            const wireEl = rootEl.closest('[wire\\:id]');
            this._wireId = wireEl ? wireEl.getAttribute('wire:id') : null;
            console.log('[tinymceEditor] init called', { uid: this.uid, wireId: this._wireId });

            this._waitForTinyAndObserve(rootEl);
        },

        _waitForTinyAndObserve(rootEl) {
            const self = this;
            const ensureTiny = (attempts = 80) => {
                if (typeof window.tinymce !== 'undefined') return self._observe(rootEl);
                if (attempts <= 0) {
                    console.error('[tinymceEditor] TinyMCE CDN nicht geladen');
                    return;
                }
                setTimeout(() => ensureTiny(attempts - 1), 100);
            };
            ensureTiny();
        },

        _observe(rootEl) {
            const self = this;
            console.log('[tinymceEditor] observe start', { offsetParent: rootEl.offsetParent, offsetHeight: rootEl.offsetHeight });
            if (rootEl.offsetParent !== null) {
                console.log('[tinymceEditor] already visible, booting');
                return self._boot();
            }

            if (typeof window.IntersectionObserver !== 'undefined') {
                const observer = new IntersectionObserver(function (entries) {
                    console.log('[tinymceEditor] intersect', entries[0].isIntersecting);
                    if (entries[0].isIntersecting && !self._editor) {
                        observer.disconnect();
                        self._boot();
                    }
                });
                observer.observe(rootEl);
            }

            // Zusaetzliches Polling als Sicherheits-Netz
            const poll = (attempts = 300) => {
                if (self._editor || attempts <= 0) return;
                if (rootEl.offsetParent !== null) {
                    console.log('[tinymceEditor] poll detected visible, booting');
                    self._boot();
                    return;
                }
                setTimeout(() => poll(attempts - 1), 200);
            };
            poll();
        },

        _boot() {
            const self = this;
            const target = document.getElementById(this.uid);
            console.log('[tinymceEditor] _boot', { uid: this.uid, targetFound: !!target });
            if (!target) {
                console.error('[tinymceEditor] target not found:', this.uid);
                return;
            }

            // Frische Initial-Daten aus Livewire holen (wire:ignore friert den
            // Blade-interpolierten Wert auf der ersten Seitenrenderung ein).
            try {
                if (this._wireId && window.Livewire) {
                    const wire = window.Livewire.find(this._wireId);
                    const parts = this.wireProperty.split('.');
                    let val = wire?.get(parts[0]);
                    for (let i = 1; i < parts.length && val; i++) val = val[parts[i]];
                    if (typeof val === 'string' && val.length > 0) {
                        this.initial = val;
                        console.log('[tinymceEditor] refreshed initial from Livewire', { length: val.length });
                    }
                }
            } catch (e) {
                console.warn('[tinymceEditor] could not refresh initial', e);
            }

            const initPromise = window.tinymce.init({
                target: target,
                license_key: 'gpl',
                height: self.height,
                min_height: self.height,
                autoresize_bottom_margin: 16,
                menubar: 'file edit view insert format table',
                plugins: 'lists table pagebreak wordcount image link autolink code',
                toolbar: 'undo redo | styles | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist | link image table | pagebreak | removeformat | code',
                image_title: true,
                image_dimensions: true,
                automatic_uploads: true,
                images_upload_handler: function (blobInfo) {
                    return new Promise(function (resolve) {
                        const reader = new FileReader();
                        reader.onload = function () { resolve(reader.result); };
                        reader.readAsDataURL(blobInfo.blob());
                    });
                },
                table_use_colgroups: false,
                promotion: false,
                branding: false,
                content_style: 'body { font-family: Arial, sans-serif; font-size: 10pt; line-height: 1.6; color: #1a1a1a; margin: 16px; }',
                setup: function (editor) {
                    self._editor = editor;
                    console.log('[tinymceEditor] setup fired');
                    editor.on('init', function () {
                        console.log('[tinymceEditor] editor init event, setting content');
                        editor.setContent(self.initial || '');
                    });
                    const sync = function () {
                        const html = editor.getContent();
                        self._syncToLivewire(html);
                    };
                    editor.on('change keyup undo redo blur', sync);
                },
            });
            if (initPromise && initPromise.then) {
                initPromise
                    .then(function (editors) {
                        console.log('[tinymceEditor] init resolved', { editorCount: editors?.length });
                    })
                    .catch(function (err) {
                        console.error('[tinymceEditor] init failed', err);
                    });
            } else {
                console.log('[tinymceEditor] init returned (no promise)');
            }
        },

        _syncToLivewire(html) {
            if (!this._wireId || !window.Livewire) return;
            try {
                const wire = window.Livewire.find(this._wireId);
                if (wire) wire.set(this.wireProperty, html, false);
            } catch (e) {
                console.warn('[tinymceEditor] sync failed', e);
            }
        },
    };
};
</script>
@endonce

<style>
    .tox-tinymce {
        display: flex !important;
        flex-direction: column !important;
    }
    .tox-tinymce .tox-edit-area {
        flex: 1 1 auto !important;
    }
    .tox-tinymce .tox-edit-area > iframe {
        height: 100% !important;
    }
</style>
<div wire:ignore
     x-data="tinymceEditor({ uid: @js($uid), wireProperty: @js($wireProperty), initial: @js((string) $initial), height: {{ (int) $height }} })"
     x-init="$nextTick(() => setTimeout(() => init(), 50))">
    <textarea id="{{ $uid }}" style="opacity:0; position:absolute; left:-9999px; pointer-events:none;"></textarea>
</div>
