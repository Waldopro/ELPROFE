// assets/js/table_enhancer.js
// Tabla ligera: búsqueda, ordenamiento y paginación sin dependencias externas.
(function () {
    function normalize(text) {
        return String(text || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase();
    }

    function initTable(table) {
        const tbody = table.querySelector('tbody');
        if (!tbody) return;

        const allRows = Array.from(tbody.querySelectorAll('tr'));
        if (allRows.length === 0) return;

        let currentPage = 1;
        let pageSize = 15;
        let sortCol = -1;
        let sortAsc = true;
        let query = '';

        const wrapper = table.closest('.table-responsive') || table.parentElement;
        const controls = document.createElement('div');
        controls.className = 'table-enhancer-controls d-flex flex-wrap gap-2 justify-content-between align-items-center px-3 py-2';
        controls.innerHTML = `
            <div class="d-flex align-items-center gap-2">
                <label class="small text-muted mb-0">Mostrar</label>
                <select class="form-select form-select-sm te-page-size" style="width: 84px;">
                    <option value="10">10</option>
                    <option value="15" selected>15</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
                <span class="small text-muted">filas</span>
            </div>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <input type="search" class="form-control form-control-sm te-search" placeholder="Buscar en tabla..." style="min-width: 230px;">
            </div>
        `;

        const footer = document.createElement('div');
        footer.className = 'table-enhancer-footer d-flex flex-wrap gap-2 justify-content-between align-items-center px-3 py-2 border-top';
        footer.innerHTML = `
            <small class="text-muted te-summary"></small>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary te-prev">Anterior</button>
                <button type="button" class="btn btn-outline-secondary te-next">Siguiente</button>
            </div>
        `;

        wrapper.parentNode.insertBefore(controls, wrapper);
        wrapper.parentNode.insertBefore(footer, wrapper.nextSibling);

        const sizeSelect = controls.querySelector('.te-page-size');
        const searchInput = controls.querySelector('.te-search');
        const summary = footer.querySelector('.te-summary');
        const btnPrev = footer.querySelector('.te-prev');
        const btnNext = footer.querySelector('.te-next');

        // Ordenamiento por encabezado
        const headers = Array.from(table.querySelectorAll('thead th'));
        headers.forEach((th, idx) => {
            th.classList.add('te-sortable');
            th.addEventListener('click', () => {
                if (sortCol === idx) sortAsc = !sortAsc;
                else {
                    sortCol = idx;
                    sortAsc = true;
                }
                render();
            });
        });

        function getFilteredRows() {
            let rows = allRows.filter((tr) => {
                if (!query) return true;
                return normalize(tr.textContent).includes(query);
            });

            if (sortCol >= 0) {
                rows = rows.slice().sort((a, b) => {
                    const ta = (a.children[sortCol]?.textContent || '').trim();
                    const tb = (b.children[sortCol]?.textContent || '').trim();
                    const na = Number(ta.replace(/[^\d.-]/g, ''));
                    const nb = Number(tb.replace(/[^\d.-]/g, ''));
                    let cmp;
                    if (Number.isFinite(na) && Number.isFinite(nb) && ta !== '' && tb !== '') {
                        cmp = na - nb;
                    } else {
                        cmp = ta.localeCompare(tb, 'es', { sensitivity: 'base', numeric: true });
                    }
                    return sortAsc ? cmp : -cmp;
                });
            }
            return rows;
        }

        function render() {
            const rows = getFilteredRows();
            const total = rows.length;
            const pages = Math.max(1, Math.ceil(total / pageSize));
            if (currentPage > pages) currentPage = pages;
            if (currentPage < 1) currentPage = 1;

            const start = (currentPage - 1) * pageSize;
            const end = Math.min(start + pageSize, total);

            allRows.forEach((tr) => (tr.style.display = 'none'));
            rows.slice(start, end).forEach((tr) => (tr.style.display = ''));

            summary.textContent = total === 0
                ? 'Sin resultados'
                : `Mostrando ${start + 1}-${end} de ${total} registro(s)`;

            btnPrev.disabled = currentPage <= 1;
            btnNext.disabled = currentPage >= pages;
        }

        sizeSelect.addEventListener('change', () => {
            pageSize = Math.max(1, parseInt(sizeSelect.value, 10) || 15);
            currentPage = 1;
            render();
        });

        searchInput.addEventListener('input', () => {
            query = normalize(searchInput.value);
            currentPage = 1;
            render();
        });

        btnPrev.addEventListener('click', () => {
            currentPage -= 1;
            render();
        });

        btnNext.addEventListener('click', () => {
            currentPage += 1;
            render();
        });

        // Si existe un buscador externo cercano, lo conectamos también.
        const externalSearch = table.closest('.card, .container, body')?.querySelector('.custom-search');
        if (externalSearch) {
            externalSearch.addEventListener('input', () => {
                searchInput.value = externalSearch.value;
                query = normalize(externalSearch.value);
                currentPage = 1;
                render();
            });
        }

        render();
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('table.datatable').forEach(initTable);
    });
})();
