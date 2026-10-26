//
// Filter dropdown wilayah berjenjang (provinsi → kabupaten/kota → kecamatan →
// desa/kelurahan). Tanpa JavaScript, semua opsi tetap tampil dan validasi
// konsistensi tetap dilakukan di server.
//
document.addEventListener('DOMContentLoaded', () => {
    const cascade = document.querySelector('[data-region-cascade]');

    if (!cascade) {
        return;
    }

    const selects = {
        province: cascade.querySelector('[data-level="province"]'),
        regency: cascade.querySelector('[data-level="regency"]'),
        district: cascade.querySelector('[data-level="district"]'),
        village: cascade.querySelector('[data-level="village"]'),
    };

    const filterChildren = (parent, child) => {
        if (!parent || !child) {
            return;
        }

        const parentValue = parent.value;
        let selectedVisible = child.value === '';

        for (const option of child.options) {
            if (option.value === '') {
                option.hidden = false;
                option.disabled = false;
                continue;
            }

            const requiredParent = option.dataset.parent ?? '';
            const visible = requiredParent === ''
                ? parentValue === ''
                : requiredParent === parentValue;

            option.hidden = !visible;
            option.disabled = !visible;

            if (option.selected && visible) {
                selectedVisible = true;
            }
        }

        if (!selectedVisible && child.value !== '') {
            child.value = '';
        }
    };

    const reset = (select) => {
        if (select) {
            select.value = '';
        }
    };

    const refresh = () => {
        filterChildren(selects.province, selects.regency);
        filterChildren(selects.regency, selects.district);
        filterChildren(selects.district, selects.village);
    };

    if (selects.province) {
        selects.province.addEventListener('change', () => {
            reset(selects.regency);
            reset(selects.district);
            reset(selects.village);
            refresh();
        });
    }

    if (selects.regency) {
        selects.regency.addEventListener('change', () => {
            reset(selects.district);
            reset(selects.village);
            refresh();
        });
    }

    if (selects.district) {
        selects.district.addEventListener('change', () => {
            reset(selects.village);
            refresh();
        });
    }

    refresh();
});
