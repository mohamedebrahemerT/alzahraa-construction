const one = (selector, root = document) => root.querySelector(selector);
const all = (selector, root = document) => [...root.querySelectorAll(selector)];

const menuToggle = one('[data-menu-toggle]');
const siteNav = one('[data-site-nav]');
menuToggle?.addEventListener('click', () => {
    const open = siteNav.classList.toggle('open');
    menuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
});

all('[data-project-filter]').forEach((button) => button.addEventListener('click', () => {
    all('[data-project-filter]').forEach((item) => item.classList.remove('active'));
    button.classList.add('active');
    const value = button.dataset.projectFilter;
    all('[data-filter-card]').forEach((card) => {
        card.hidden = value !== 'all' && card.dataset.category !== value;
    });
}));

all('form[data-ajax-form]').forEach((form) => form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = one('[type="submit"]', form);
    const feedback = one('[data-form-feedback]', form) || one('[data-form-feedback]');
    const original = button?.textContent;
    if (button) { button.disabled = true; button.textContent = 'جارٍ الحفظ…'; }
    if (feedback) { feedback.textContent = ''; feedback.className = 'form-feedback'; }
    try {
        const response = await fetch(form.action, {
            method: (form.method || 'POST').toUpperCase(), body: new FormData(form),
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) {
            const message = result.message || Object.values(result.errors || {}).flat()[0] || 'تعذّر الحفظ. راجع الحقول وحاول مرة أخرى.';
            throw new Error(message);
        }
        if (feedback) { feedback.textContent = result.message || 'تم الحفظ بنجاح.'; feedback.classList.add('form-success'); }
        if (form.dataset.reload === 'true') window.setTimeout(() => window.location.reload(), 500);
        else form.reset();
    } catch (error) {
        if (feedback) { feedback.textContent = error.message; feedback.classList.add('form-errors'); }
    } finally {
        if (button) { button.disabled = false; button.textContent = original || 'حفظ'; }
    }
}));

const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[char]));
const sectionTypes = {
    hero:'البانر الرئيسي', 'page-hero':'بانر صفحة', stats:'الإحصائيات', 'text-image':'نص وصورة',
    services:'الخدمات', process:'خطوات العمل', 'before-after':'قبل وبعد', features:'لماذا نحن',
    projects:'المشاريع', cta:'دعوة لاتخاذ إجراء', values:'الرؤية والرسالة والقيم', team:'الفريق',
    gallery:'معرض صور', equipment:'المعدات', contact:'نموذج التواصل',
};

const builder = one('[data-sections-editor]');
if (builder) {
    const source = one('[data-sections-json]', builder);
    const list = one('[data-sections-list]', builder);
    const options = window.siteMedia || [];
    let sections = [];
    try { sections = JSON.parse(source.value || '[]'); } catch { sections = []; }
    const sync = () => { source.value = JSON.stringify(sections); };
    const mediaPicker = (section, index) => {
        const selected = section.image || '';
        return `<div class="mini-field"><label>الصورة الرئيسية</label><select data-index="${index}" data-key="image"><option value="">بدون صورة</option>${options.map((media) => `<option value="${esc(media.path)}" ${media.path === selected ? 'selected' : ''}>${esc(media.alt || media.name)}</option>`).join('')}</select></div>`;
    };
    const englishFields = (index, values, itemIndex = null, keys = Object.keys(values)) => {
        const labels = { value:'القيمة', label:'العنوان القصير', title:'العنوان', text:'الوصف', alt:'النص البديل للصورة', primary_label:'نص الزر الأساسي', secondary_label:'نص الزر الثاني', link_label:'نص الرابط' };
        return `<details class="section-translation-fields"><summary>English (optional)</summary><div class="section-editor-translations">${Object.entries(labels).filter(([key]) => keys.includes(key)).map(([key, label]) => {
            const value = values[key] ?? '';
            const attrs = `data-index="${index}" data-key="${key}" data-locale="en" ${itemIndex === null ? '' : `data-item="${itemIndex}"`}`;
            return `<div class="mini-field"><label>${label}</label>${key === 'text' ? `<textarea ${attrs}>${esc(value)}</textarea>` : `<input ${attrs} value="${esc(value)}">`}</div>`;
        }).join('')}</div></details>`;
    };
    const renderItems = (section, index) => {
        if (!Array.isArray(section.items) && !['stats','process','features','values'].includes(section.type)) return '';
        section.items ||= [];
        const fields = ['value','label','title','text'];
        return `<div class="mini-field wide"><label>محتوى البطاقات والعناصر</label>${section.items.map((item, itemIndex) => `<div class="section-item-row">${fields.map((key) => `<div class="mini-field"><label>${key === 'value' ? 'القيمة' : key === 'label' ? 'العنوان القصير' : key === 'title' ? 'العنوان' : 'الوصف'}</label><input data-index="${index}" data-item="${itemIndex}" data-key="${key}" value="${esc(item?.[key] ?? '')}"></div>`).join('')}${englishFields(index, item?.translations?.en || {}, itemIndex, fields)}<button class="btn btn-outline-dark btn-sm" type="button" data-remove-item="${itemIndex}" data-index="${index}">حذف هذا العنصر</button></div>`).join('')}<button class="btn btn-outline-dark btn-sm" type="button" data-add-item="${index}">إضافة عنصر</button></div>`;
    };
    const renderImages = (section, index) => {
        if (section.type !== 'gallery') return '';
        const chosen = section.images || [];
        return `<div class="mini-field wide"><label>صور المعرض</label><div class="section-checkboxes">${options.map((media) => `<label><input type="checkbox" data-image-index="${index}" value="${esc(media.path)}" ${chosen.includes(media.path) ? 'checked' : ''}>${esc(media.alt || media.name)}</label>`).join('')}</div></div>`;
    };
    const render = () => {
        list.innerHTML = sections.map((section, index) => `<article class="section-editor" draggable="true" data-section-card="${index}"><div class="section-editor-head"><strong>☷ ${esc(sectionTypes[section.type] || section.type)} ${section.visible === false ? '— مخفي' : ''}</strong><div class="admin-actions"><button type="button" class="btn btn-outline-dark btn-sm" data-move="up" data-index="${index}" aria-label="تحريك لأعلى">↑</button><button type="button" class="btn btn-outline-dark btn-sm" data-move="down" data-index="${index}" aria-label="تحريك لأسفل">↓</button><button type="button" class="btn btn-outline-dark btn-sm" data-duplicate="${index}">تكرار</button><button type="button" class="btn btn-outline-dark btn-sm" data-remove="${index}">حذف</button></div></div><div class="section-editor-body"><div class="mini-field"><label>نوع القسم</label><select data-index="${index}" data-key="type">${Object.entries(sectionTypes).map(([key, label]) => `<option value="${key}" ${key === section.type ? 'selected' : ''}>${label}</option>`).join('')}</select></div><div class="mini-field"><label><input type="checkbox" data-index="${index}" data-key="visible" ${section.visible !== false ? 'checked' : ''}> إظهار القسم</label></div><div class="mini-field"><label>عنوان القسم</label><input data-index="${index}" data-key="title" value="${esc(section.title)}"></div><div class="mini-field"><label>الخلفية</label><select data-index="${index}" data-key="background">${[['light','فاتح'],['white','أبيض'],['sand','رملي'],['navy','كحلي']].map(([v,l])=>`<option value="${v}" ${(section.background||'light')===v?'selected':''}>${l}</option>`).join('')}</select></div><div class="mini-field wide"><label>النص</label><textarea data-index="${index}" data-key="text">${esc(section.text)}</textarea></div>${mediaPicker(section,index)}<div class="mini-field"><label>النص البديل للصورة</label><input data-index="${index}" data-key="alt" value="${esc(section.alt||'')}"></div>${section.type === 'hero' ? `<div class="mini-field"><label><input type="checkbox" data-index="${index}" data-key="image_has_text" ${section.image_has_text ? 'checked' : ''}> النص الأساسي مكتوب داخل الصورة</label></div>` : ''}<div class="mini-field"><label>المحاذاة</label><select data-index="${index}" data-key="align">${[['right','يمين'],['center','وسط'],['left','يسار']].map(([v,l])=>`<option value="${v}" ${(section.align||'right')===v?'selected':''}>${l}</option>`).join('')}</select></div><div class="mini-field"><label>الأعمدة</label><select data-index="${index}" data-key="columns">${[1,2,3,4].map((n)=>`<option value="${n}" ${(Number(section.columns||2)===n)?'selected':''}>${n}</option>`).join('')}</select></div><div class="mini-field"><label>المسافات</label><select data-index="${index}" data-key="spacing">${[['compact','مضغوط'],['normal','عادي'],['spacious','واسع']].map(([v,l])=>`<option value="${v}" ${(section.spacing||'normal')===v?'selected':''}>${l}</option>`).join('')}</select></div>${['primary_label','primary_url','secondary_label','secondary_url','link_label','link_url'].map((key)=>`<div class="mini-field"><label>${({primary_label:'نص الزر الأساسي',primary_url:'رابط الزر الأساسي',secondary_label:'نص الزر الثاني',secondary_url:'رابط الزر الثاني',link_label:'نص الرابط',link_url:'الرابط'})[key]}</label><input data-index="${index}" data-key="${key}" value="${esc(section[key]||'')}"></div>`).join('')}${englishFields(index, section.translations?.en || {}, null, ['title','text','alt','primary_label','secondary_label','link_label'])}${renderItems(section,index)}${renderImages(section,index)}</div></article>`).join('');
        sync();
    };
    list.addEventListener('input', (event) => {
        const field = event.target.closest('[data-index][data-key]'); if (!field) return;
        const section = sections[Number(field.dataset.index)];
        const itemIndex = field.dataset.item;
        if (itemIndex !== undefined) {
            section.items[Number(itemIndex)] ||= {};
            const item = section.items[Number(itemIndex)];
            if (field.dataset.locale === 'en') { item.translations ||= {}; item.translations.en ||= {}; item.translations.en[field.dataset.key] = field.value; }
            else item[field.dataset.key] = field.value;
        } else if (field.dataset.locale === 'en') { section.translations ||= {}; section.translations.en ||= {}; section.translations.en[field.dataset.key] = field.value; }
        else if (field.type === 'checkbox') section[field.dataset.key] = field.checked;
        else section[field.dataset.key] = field.value;
        sync();
    });
    list.addEventListener('change', (event) => {
        if (event.target.matches('[data-key="type"]')) { render(); return; }
        const check = event.target.closest('[data-image-index]'); if (!check) return;
        const section = sections[Number(check.dataset.imageIndex)];
        section.images ||= [];
        section.images = check.checked ? [...new Set([...section.images, check.value])] : section.images.filter((image) => image !== check.value);
        sync();
    });
    list.addEventListener('click', (event) => {
        const target = event.target.closest('button'); if (!target) return;
        if (target.hasAttribute('data-remove')) sections.splice(Number(target.dataset.remove), 1);
        if (target.hasAttribute('data-duplicate')) { const copy = structuredClone(sections[Number(target.dataset.duplicate)]); copy.id = crypto.randomUUID().slice(0, 10); sections.splice(Number(target.dataset.duplicate) + 1, 0, copy); }
        if (target.hasAttribute('data-add-item')) sections[Number(target.dataset.addItem)].items = [...(sections[Number(target.dataset.addItem)].items || []), { value:'', label:'', title:'', text:'' }];
        if (target.hasAttribute('data-remove-item')) sections[Number(target.dataset.index)].items.splice(Number(target.dataset.removeItem), 1);
        if (target.hasAttribute('data-move')) {
            const index = Number(target.dataset.index), to = index + (target.dataset.move === 'up' ? -1 : 1);
            if (to >= 0 && to < sections.length) [sections[index], sections[to]] = [sections[to], sections[index]];
        }
        render();
    });
    let dragging = null;
    list.addEventListener('dragstart', (event) => { dragging = Number(event.target.closest('[data-section-card]')?.dataset.sectionCard); });
    list.addEventListener('dragover', (event) => event.preventDefault());
    list.addEventListener('drop', (event) => {
        event.preventDefault(); const target = Number(event.target.closest('[data-section-card]')?.dataset.sectionCard);
        if (Number.isInteger(dragging) && Number.isInteger(target) && dragging !== target) { const [moving] = sections.splice(dragging,1); sections.splice(target,0,moving); render(); }
        dragging = null;
    });
    one('[data-add-section]', builder)?.addEventListener('click', () => {
        sections.push({ id: crypto.randomUUID().slice(0,10), type:'text-image', title:'قسم جديد', text:'', image:'', visible:true, background:'light', align:'right', columns:2, spacing:'normal' });
        render(); list.lastElementChild?.scrollIntoView({ behavior:'smooth', block:'center' });
    });
    render();
}

all('[data-preview-size]').forEach((button) => button.addEventListener('click', () => {
    const frame = one('[data-preview-frame]'); if (!frame) return;
    frame.classList.toggle('mobile', button.dataset.previewSize === 'mobile');
}));
