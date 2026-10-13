/* Record popup: shows every field of a record without leaving the list. */
(() => {
  const dataNode = document.getElementById('record-data');
  const modal = document.getElementById('recordModal');
  if (!dataNode || !modal) return;

  let records = {};
  try {
    records = JSON.parse(dataNode.textContent || '{}');
  } catch (error) {
    return;
  }

  const kindNode = document.getElementById('recordModalKind');
  const titleNode = document.getElementById('recordModalTitle');
  const bodyNode = document.getElementById('recordModalBody');
  const footNode = document.getElementById('recordModalFoot');
  let lastFocused = null;

  const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[c]));

  const renderValue = (field) => {
    const raw = (field.raw || '').trim();
    if (raw === '') return '<span class="detail-muted">Not filled in</span>';
    if (field.type === 'url') {
      const href = escapeHtml(raw);
      return `<a class="text-button" href="${href}" target="_blank" rel="noopener noreferrer">${href}</a>`;
    }
    if (field.type === 'textarea') return `<span class="detail-text">${escapeHtml(raw).replace(/\n/g, '<br>')}</span>`;
    return escapeHtml(field.text);
  };

  const open = (id) => {
    const record = records[id];
    if (!record) return;
    lastFocused = document.activeElement;
    kindNode.textContent = record.kind.toUpperCase();
    titleNode.textContent = record.title;
    bodyNode.innerHTML = record.fields.map((field) => `
      <div class="record-row">
        <span class="record-row-label">${escapeHtml(field.label)}</span>
        <span class="record-row-value">${renderValue(field)}</span>
      </div>`).join('');
    footNode.innerHTML = record.editUrl
      ? `<a class="button button-primary" href="${escapeHtml(record.editUrl)}">Edit this record</a>`
      : '';
    modal.hidden = false;
    document.body.classList.add('modal-open');
    modal.querySelector('[data-modal-close]').focus();
  };

  const close = () => {
    modal.hidden = true;
    document.body.classList.remove('modal-open');
    if (lastFocused) lastFocused.focus();
  };

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-record]');
    if (trigger) {
      event.preventDefault();
      open(trigger.dataset.record);
      return;
    }
    if (event.target.closest('[data-modal-close]') || event.target === modal) close();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !modal.hidden) close();
  });
})();
