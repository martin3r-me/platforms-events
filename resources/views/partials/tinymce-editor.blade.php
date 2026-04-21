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
            // Entferne Placeholder
            const placeholder = this.$root.querySelector('.tinymce-placeholder');
            if (placeholder) placeholder.remove();

            window.tinymce.init({
                target: target,
                license_key: 'gpl',
                height: self.height,
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
                    editor.on('init', function () {
                        editor.setContent(self.initial || '');
                    });
                    const sync = function () {
                        const html = editor.getContent();
                        self._syncToLivewire(html);
                    };
                    editor.on('change keyup undo redo blur', sync);
                },
            }).catch(function (err) {
                console.error('[tinymceEditor] init failed', err);
            });
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

<div wire:ignore
     x-data="tinymceEditor({ uid: @js($uid), wireProperty: @js($wireProperty), initial: @js((string) $initial), height: {{ (int) $height }} })"
     x-init="$nextTick(() => setTimeout(() => init(), 50))"
     style="min-height: {{ (int) $height }}px; border: 1px dashed #cbd5e1; border-radius: 6px; padding: 8px; background: #f8fafc;">
    <div class="tinymce-placeholder" style="color: #64748b; font-size: 0.7rem; padding: 6px;">
        Editor wird geladen (uid: {{ $uid }}) …
    </div>
    <textarea id="{{ $uid }}" style="display:none;"></textarea>
</div>
