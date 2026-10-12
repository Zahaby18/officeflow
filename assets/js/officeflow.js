(() => {
  const sidebar = document.getElementById('sidebar');
  const pageContent = document.getElementById('pageContent');
  const homeMarkup = pageContent.innerHTML;
  const breadcrumbTitle = document.getElementById('breadcrumbTitle');
  const menuToggle = document.getElementById('menuToggle');
  const mobileScrim = document.getElementById('mobileScrim');
  const notificationButton = document.getElementById('notificationButton');
  const notificationPanel = document.getElementById('notificationPanel');
  const notificationDot = document.getElementById('notificationDot');
  const toast = document.getElementById('toast');
  const requestModal = document.getElementById('requestModal');
  const requestForm = document.getElementById('requestForm');
  const requestTitle = document.getElementById('requestTitle');
  const newRequestButton = document.getElementById('newRequestButton');
  let toastTimer;
  let lastFocusedElement;
  let currentPage = 'Home';

  const pages = {
    Requests: { icon: '#i-inbox', description: 'Capture office needs and keep every request moving to the right person.' },
    'Tasks & Checklists': { icon: '#i-checklist', description: 'Keep recurring routines and one-off office tasks clear for the team.' },
    Announcements: { icon: '#i-megaphone', description: 'Share important office updates where the whole team can find them.' },
    Assets: { icon: '#i-box', description: 'Know what the office owns, where it is, and when it needs attention.' },
    Inventory: { icon: '#i-package', description: 'Track everyday supplies before the cupboard runs empty.' },
    Maintenance: { icon: '#i-tool', description: 'Keep repairs, inspections, and service history in one place.' },
    'Rooms & Facilities': { icon: '#i-building', description: 'Manage shared rooms, facilities, and their day-to-day readiness.' },
    Vendors: { icon: '#i-users', description: 'Keep supplier details and service notes close to the work.' },
    'Documents & SOPs': { icon: '#i-file', description: 'Give the team one place to find current procedures and references.' },
    Team: { icon: '#i-users', description: 'Manage office members and their access to OfficeFlow.' },
    Reports: { icon: '#i-chart', description: 'Review office workload and operational trends.' },
    'Activity log': { icon: '#i-clock', description: 'See a record of important changes made in the workspace.' },
    Settings: { icon: '#i-settings', description: 'Set up your workspace, categories, roles, and preferences.' },
  };

  function showToast(message) {
    toast.textContent = message;
    toast.classList.add('is-visible');
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => toast.classList.remove('is-visible'), 2600);
  }

  function setMobileMenu(isOpen) {
    sidebar.classList.toggle('is-open', isOpen);
    mobileScrim.hidden = !isOpen;
    menuToggle.setAttribute('aria-expanded', String(isOpen));
    menuToggle.setAttribute('aria-label', isOpen ? 'Close navigation' : 'Open navigation');
  }

  function closeNotifications() {
    notificationPanel.hidden = true;
    notificationButton.setAttribute('aria-expanded', 'false');
  }

  function showPage(name) {
    currentPage = name;
    closeNotifications();
    setMobileMenu(false);
    document.querySelectorAll('.nav-link[data-page]').forEach((link) => {
      link.classList.toggle('is-active', link.dataset.page === name);
    });
    breadcrumbTitle.textContent = name;

    if (name === 'Home') {
      pageContent.innerHTML = homeMarkup;
      bindHomeControls();
      window.history.replaceState(null, '', `${window.location.pathname}${window.location.search}`);
      return;
    }

    const page = pages[name];
    if (!page) return;

    const actionLabel = name === 'Requests'
      ? 'New request'
      : name === 'Tasks & Checklists'
        ? 'New task'
        : `Add ${name.toLowerCase()}`;

    pageContent.innerHTML = `
      <section class="page-heading">
        <div><p class="eyebrow">OFFICEFLOW WORKSPACE</p><h1>${name}</h1><p class="heading-copy">${page.description}</p></div>
        <div class="heading-actions">
          <button class="button button-secondary" type="button" data-preview-filter><svg class="icon"><use href="#i-calendar"/></svg><span>All activity</span></button>
          <button class="button button-primary" type="button" data-preview-action><svg class="icon"><use href="#i-plus"/></svg><span>${actionLabel}</span></button>
        </div>
      </section>
      <section class="surface page-placeholder">
        <div><span class="placeholder-icon"><svg class="icon"><use href="${page.icon}"/></svg></span>
          <h2>${name} workspace</h2>
          <p>This module is shown as a UI preview. Its fields, workflow, and access rules will be connected to the app in later implementation slices.</p>
          <button class="button button-secondary" type="button" data-page="Home"><svg class="icon"><use href="#i-home"/></svg><span>Back to home</span></button>
          <div class="placeholder-note">Preview only · Sample data · Nothing is saved</div>
        </div>
      </section>
      <footer class="preview-footer"><span>OfficeFlow preview</span><span>Sample workspace · Demo data only</span></footer>`;

    window.history.replaceState(null, '', `${window.location.pathname}${window.location.search}#${encodeURIComponent(name.toLowerCase().replaceAll(' ', '-'))}`);
  }

  function openRequestModal() {
    lastFocusedElement = document.activeElement;
    requestModal.hidden = false;
    document.body.classList.add('modal-open');
    requestTitle.focus();
  }

  function closeRequestModal() {
    requestModal.hidden = true;
    document.body.classList.remove('modal-open');
    lastFocusedElement?.focus();
  }

  function bindHomeControls() {
    document.getElementById('newRequestButton')?.addEventListener('click', openRequestModal);
    document.getElementById('todayButton')?.addEventListener('click', () => showToast('Showing today’s office overview.'));
    document.querySelectorAll('.task-checkbox').forEach((checkbox) => {
      checkbox.addEventListener('change', () => {
        checkbox.closest('.task-row').classList.toggle('is-done', checkbox.checked);
        const completed = document.querySelectorAll('.task-checkbox:checked').length;
        document.getElementById('taskProgress').textContent = `${completed} of 4`;
        showToast(checkbox.checked ? 'Task marked complete in this preview.' : 'Task reopened in this preview.');
      });
    });
  }

  document.addEventListener('click', (event) => {
    const pageButton = event.target.closest('[data-page]');
    if (pageButton) {
      showPage(pageButton.dataset.page);
      return;
    }

    if (event.target.closest('[data-preview-action]')) {
      if (currentPage === 'Requests') {
        openRequestModal();
      } else {
        showToast('This action will be connected when this module is implemented.');
      }
      return;
    }

    if (event.target.closest('[data-preview-filter]')) {
      showToast('Filters are not connected in the UI preview.');
      return;
    }

    if (!notificationPanel.contains(event.target) && !notificationButton.contains(event.target)) {
      closeNotifications();
    }
  });

  menuToggle.addEventListener('click', () => setMobileMenu(!sidebar.classList.contains('is-open')));
  mobileScrim.addEventListener('click', () => setMobileMenu(false));
  notificationButton.addEventListener('click', () => {
    const willOpen = notificationPanel.hidden;
    notificationPanel.hidden = !willOpen;
    notificationButton.setAttribute('aria-expanded', String(willOpen));
  });

  document.getElementById('markRead').addEventListener('click', () => {
    notificationPanel.querySelectorAll('.unread-mark').forEach((mark) => { mark.hidden = true; });
    notificationDot.hidden = true;
    notificationButton.setAttribute('aria-label', 'Notifications, all read');
    document.getElementById('notificationCount').textContent = 'all caught up';
    showToast('Notifications marked as read in this preview.');
  });

  document.getElementById('closeModalButton').addEventListener('click', closeRequestModal);
  document.getElementById('cancelRequestButton').addEventListener('click', closeRequestModal);
  requestModal.addEventListener('click', (event) => {
    if (event.target === requestModal) closeRequestModal();
  });
  requestForm.addEventListener('submit', (event) => {
    event.preventDefault();
    if (!requestForm.reportValidity()) return;
    closeRequestModal();
    requestForm.reset();
    showToast('Request preview submitted. No data has been saved.');
  });

  document.getElementById('globalSearch').addEventListener('keydown', (event) => {
    if (event.key === 'Enter' && event.currentTarget.value.trim()) {
      showToast(`Search preview: ${event.currentTarget.value.trim()}`);
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      if (!requestModal.hidden) closeRequestModal();
      closeNotifications();
      setMobileMenu(false);
    }
  });

  bindHomeControls();
})();
