(function () {
    const searchInput = document.getElementById('faqSearch');
    if (!searchInput) return;

    const clearBtns  = [document.getElementById('faqClear'), document.getElementById('faqClear2')];
    const filterBtns = document.querySelectorAll('.faq-filter-btn');
    const groups     = document.querySelectorAll('.faq-group');
    const items      = document.querySelectorAll('.faq-item');
    const empty      = document.getElementById('faqEmpty');

    let activeCat = 'all';

    items.forEach(item => {
        const btn = item.querySelector('.faq-question');
        btn.addEventListener('click', () => item.classList.toggle('open'));
    });

    function applyFilters() {
        const q = (searchInput.value || '').trim().toLowerCase();
        let visibleCount = 0;

        groups.forEach(group => {
            const groupCat = group.dataset.cat;
            const catOk = activeCat === 'all' || activeCat === groupCat;
            let groupVisible = 0;

            group.querySelectorAll('.faq-item').forEach(item => {
                const matchText = !q
                    || (item.dataset.q || '').includes(q)
                    || (item.dataset.a || '').includes(q);

                if (catOk && matchText) {
                    item.style.display = '';
                    groupVisible++;
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            group.style.display = groupVisible > 0 ? '' : 'none';
        });

        if (empty) empty.style.display = visibleCount === 0 ? 'block' : 'none';
    }

    searchInput.addEventListener('input', applyFilters);

    clearBtns.forEach(btn => {
        if (!btn) return;
        btn.addEventListener('click', () => {
            searchInput.value = '';
            activeCat = 'all';
            filterBtns.forEach(b => b.classList.toggle('active', b.dataset.cat === 'all'));
            applyFilters();
            searchInput.focus();
        });
    });

    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            activeCat = btn.dataset.cat;
            filterBtns.forEach(b => b.classList.toggle('active', b === btn));
            applyFilters();
        });
    });
})();