@once
    <div
        id="markWaitlistContactModal"
        class="fixed inset-0 z-[60] hidden items-center justify-center bg-slate-900/50 px-4 py-6 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        aria-labelledby="markWaitlistContactModalTitle"
    >
        <div class="crm-modal max-w-lg">
            <div class="mb-4 flex items-center justify-between">
                <h2 id="markWaitlistContactModalTitle" class="text-lg font-semibold text-slate-900">Log contact</h2>
                <button type="button" class="rounded p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-800" data-close-waitlist-contact-modal aria-label="Close">✕</button>
            </div>
            <form id="markWaitlistContactForm" method="POST" action="#" class="space-y-4">
                @csrf
                <input type="hidden" name="return_to" value="appointments">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-800" for="waitlist_contacted_at">When was the contact?</label>
                    <input
                        id="waitlist_contacted_at"
                        name="contacted_at"
                        type="datetime-local"
                        class="crm-input"
                        required
                    >
                    <p class="mt-1 text-xs text-slate-500">Defaults to now; adjust if the conversation happened earlier.</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-800" for="waitlist_contact_method">Method</label>
                    <select id="waitlist_contact_method" name="contact_method" class="crm-input" required>
                        @foreach (\App\Support\ContactMethod::KEYS as $method)
                            <option value="{{ $method }}">{{ \App\Support\ContactMethod::label($method) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-800" for="waitlist_contact_notes">Interaction notes</label>
                    <textarea
                        id="waitlist_contact_notes"
                        name="contact_notes"
                        rows="4"
                        class="crm-input"
                        required
                        placeholder="How did it go? Next steps, objections, etc."
                    ></textarea>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50" data-close-waitlist-contact-modal>
                        Cancel
                    </button>
                    <button type="submit" class="rounded-lg bg-pink-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-pink-700">
                        Save &amp; mark contacted
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            function pad(n) {
                return String(n).padStart(2, '0');
            }
            function localDatetimeLocalValue(d) {
                return (
                    d.getFullYear() +
                    '-' +
                    pad(d.getMonth() + 1) +
                    '-' +
                    pad(d.getDate()) +
                    'T' +
                    pad(d.getHours()) +
                    ':' +
                    pad(d.getMinutes())
                );
            }
            function openModal(btn) {
                const modal = document.getElementById('markWaitlistContactModal');
                const form = document.getElementById('markWaitlistContactForm');
                if (!modal || !form) return;
                const url = btn.getAttribute('data-contact-url');
                if (!url) return;
                form.setAttribute('action', url);
                const ret = btn.getAttribute('data-return-to') || 'appointments';
                const retInput = form.querySelector('input[name=return_to]');
                if (retInput) retInput.value = ret;
                const dt = form.querySelector('[name=contacted_at]');
                if (dt) dt.value = localDatetimeLocalValue(new Date());
                const notes = form.querySelector('[name=contact_notes]');
                if (notes) notes.value = '';
                const method = form.querySelector('[name=contact_method]');
                if (method) method.selectedIndex = 0;
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                if (dt) dt.focus();
            }
            function closeModal() {
                const modal = document.getElementById('markWaitlistContactModal');
                if (!modal) return;
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
            document.addEventListener('click', function (e) {
                const openBtn = e.target.closest('[data-open-waitlist-contact]');
                if (openBtn) {
                    e.preventDefault();
                    openModal(openBtn);
                    return;
                }
                if (e.target.closest('[data-close-waitlist-contact-modal]')) {
                    e.preventDefault();
                    closeModal();
                    return;
                }
                const modal = document.getElementById('markWaitlistContactModal');
                if (modal && e.target === modal) {
                    closeModal();
                }
            });
            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape') return;
                const modal = document.getElementById('markWaitlistContactModal');
                if (modal && !modal.classList.contains('hidden')) {
                    closeModal();
                }
            });
        })();
    </script>
@endonce
