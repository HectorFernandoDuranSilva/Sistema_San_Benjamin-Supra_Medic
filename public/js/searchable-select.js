(function () {
    const sources = new WeakMap();
    const normalize = value => String(value || '').normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '').toLowerCase();

    window.filterSearchableSelect = function (select, term, onChange, includeDataset = false) {
        if (!sources.has(select)) {
            sources.set(select, Array.from(select.options, option => option.cloneNode(true)));
        }
        const previous = select.value;
        const words = normalize(term).trim().split(/\s+/).filter(Boolean);
        const matches = sources.get(select).filter(option => {
            const text = includeDataset
                ? (option.dataset.busqueda || `${option.text} ${option.dataset.documento || ''}`)
                : option.text;
            return words.every(word => normalize(text).includes(word));
        });
        select.replaceChildren(...matches.map(option => option.cloneNode(true)));
        if (!matches.length) {
            const empty = new Option('Sin coincidencias', '');
            empty.disabled = true;
            select.add(empty);
            select.selectedIndex = 0;
        } else if (matches.some(option => option.value === previous)) {
            select.value = previous;
        } else {
            select.selectedIndex = 0;
        }
        onChange();
    };
})();
