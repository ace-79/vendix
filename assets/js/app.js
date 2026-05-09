// Vendix - Ecommerce Management System Application JavaScript

const VENDIX_TOAST_STORAGE_KEY = 'vendix_pending_notification';
const VENDIX_SESSION_MONITOR_INTERVAL_MS = 5000;
const VENDIX_SESSION_WARNING_KEY = 'vendix_session_warning';
const VENDIX_CSRF_META_NAME = 'vendix-csrf-token';
const vendixOriginalFetch = window.fetch.bind(window);

document.addEventListener('DOMContentLoaded', function () {
    console.log('Vendix loaded');
    syncCsrfFields();
    initializeEventListeners();
    flushPendingNotification();
    startSessionMonitor();
});

function getCsrfToken() {
    const meta = document.querySelector(`meta[name="${VENDIX_CSRF_META_NAME}"]`);
    return meta ? meta.getAttribute('content') || '' : '';
}

function syncCsrfFields() {
    const token = getCsrfToken();

    if (!token) {
        return;
    }

    document.querySelectorAll('form').forEach((form) => {
        const method = (form.getAttribute('method') || 'GET').toUpperCase();

        if (method !== 'POST') {
            return;
        }

        let field = form.querySelector('input[name="_csrf_token"]');

        if (!field) {
            field = document.createElement('input');
            field.type = 'hidden';
            field.name = '_csrf_token';
            form.appendChild(field);
        }

        field.value = token;
    });
}

function isStateChangingMethod(method) {
    return ['POST', 'PUT', 'PATCH', 'DELETE'].includes(String(method || 'GET').toUpperCase());
}

function isSameOriginRequest(input) {
    try {
        const requestUrl = input instanceof Request ? input.url : input;
        const url = new URL(requestUrl, window.location.href);
        return url.origin === window.location.origin;
    } catch (error) {
        console.error('Error checking request origin:', error);
        return false;
    }
}

function buildCsrfRequest(input, init = {}) {
    const request = new Request(input, init);

    if (!isSameOriginRequest(request) || !isStateChangingMethod(request.method)) {
        return request;
    }

    const headers = new Headers(request.headers || {});
    const token = getCsrfToken();

    if (token) {
        headers.set('X-CSRF-Token', token);
    }

    return new Request(request, { headers });
}

window.fetch = function vendixFetch(input, init = {}) {
    const request = buildCsrfRequest(input, init);
    return vendixOriginalFetch(request);
};

// Initialize event listeners
function initializeEventListeners() {
    // Add click handlers for buttons
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('click', function (e) {
            // Handle button clicks
        });
    });
}

// Fetch all products
async function fetchProducts() {
    try {
        const response = await fetch('api/products.php');
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error fetching products:', error);
    }
}

// Fetch all customers
async function fetchCustomers() {
    try {
        const response = await fetch('api/customers.php');
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error fetching customers:', error);
    }
}

// Fetch all sales
async function fetchSales() {
    try {
        const response = await fetch('api/sales.php');
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error fetching sales:', error);
    }
}

// Create new product
async function createProduct(productData) {
    try {
        const response = await fetch('api/products.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(productData)
        });
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error creating product:', error);
    }
}

// Create new customer
async function createCustomer(customerData) {
    try {
        const response = await fetch('api/customers.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(customerData)
        });
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error creating customer:', error);
    }
}

// Create new sale
async function createSale(saleData) {
    try {
        const response = await fetch('api/sales.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(saleData)
        });
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error creating sale:', error);
    }
}

function getNotificationContainer() {
    let container = document.getElementById('vendix-notification-container');

    if (!container) {
        container = document.createElement('div');
        container.id = 'vendix-notification-container';
        container.className = 'vendix-notification-container';
        document.body.appendChild(container);
    }

    return container;
}

function getConfirmDialog() {
    let overlay = document.getElementById('vendix-confirm-overlay');

    if (overlay) {
        return overlay;
    }

    overlay = document.createElement('div');
    overlay.id = 'vendix-confirm-overlay';
    overlay.className = 'vendix-confirm-overlay';
    overlay.innerHTML = `
        <div class="vendix-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="vendix-confirm-title" aria-describedby="vendix-confirm-message" tabindex="-1">
            <div class="vendix-confirm-icon">
                <i class="fas fa-triangle-exclamation"></i>
            </div>
            <h3 id="vendix-confirm-title" class="vendix-confirm-title">Please Confirm</h3>
            <p id="vendix-confirm-message" class="vendix-confirm-message"></p>
            <div class="vendix-confirm-actions">
                <button type="button" class="btn vendix-confirm-cancel">Cancel</button>
                <button type="button" class="btn vendix-confirm-accept">Delete</button>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);
    return overlay;
}

function getNotificationType(type, message) {
    if (type) {
        return type;
    }

    const text = String(message || '').toLowerCase();

    if (text.includes('error') || text.includes('failed') || text.includes('cannot')) {
        return 'error';
    }

    if (text.includes('success') || text.includes('saved') || text.includes('updated') || text.includes('deleted') || text.includes('created') || text.includes('changed')) {
        return 'success';
    }

    if (text.includes('warning') || text.includes('please')) {
        return 'warning';
    }

    return 'info';
}

// Show notification
function showNotification(message, type = null, duration = 4000) {
    const container = getNotificationContainer();
    const notificationType = getNotificationType(type, message);
    const notification = document.createElement('div');
    const icon = document.createElement('div');
    const content = document.createElement('div');
    const closeButton = document.createElement('button');
    const iconMap = {
        success: 'fa-check-circle',
        error: 'fa-circle-xmark',
        warning: 'fa-triangle-exclamation',
        info: 'fa-circle-info'
    };

    notification.className = `vendix-toast vendix-toast-${notificationType}`;
    notification.setAttribute('role', 'status');
    notification.setAttribute('aria-live', 'polite');

    icon.className = 'vendix-toast-icon';
    icon.innerHTML = `<i class="fas ${iconMap[notificationType] || iconMap.info}"></i>`;

    content.className = 'vendix-toast-message';
    content.textContent = String(message || '');

    closeButton.className = 'vendix-toast-close';
    closeButton.type = 'button';
    closeButton.setAttribute('aria-label', 'Close notification');
    closeButton.innerHTML = '&times;';

    const removeNotification = () => {
        if (!notification.parentNode) {
            return;
        }

        notification.classList.add('is-hiding');
        setTimeout(() => notification.remove(), 220);
    };

    closeButton.addEventListener('click', removeNotification);

    notification.appendChild(icon);
    notification.appendChild(content);
    notification.appendChild(closeButton);
    container.appendChild(notification);

    requestAnimationFrame(() => {
        notification.classList.add('is-visible');
    });

    window.setTimeout(removeNotification, duration);
}

function persistNotification(message, type = null) {
    try {
        sessionStorage.setItem(VENDIX_TOAST_STORAGE_KEY, JSON.stringify({
            message: String(message || ''),
            type: getNotificationType(type, message)
        }));
    } catch (error) {
        console.error('Error storing notification:', error);
    }
}

function flushPendingNotification() {
    try {
        const raw = sessionStorage.getItem(VENDIX_TOAST_STORAGE_KEY);

        if (!raw) {
            return;
        }

        sessionStorage.removeItem(VENDIX_TOAST_STORAGE_KEY);
        const notification = JSON.parse(raw);

        if (notification && notification.message) {
            showNotification(notification.message, notification.type);
        }
    } catch (error) {
        console.error('Error restoring notification:', error);
    }
}

function notifyAndReload(message, type = null) {
    persistNotification(message, type);
    window.location.reload();
}

function shouldMonitorSession() {
    const path = window.location.pathname.toLowerCase();
    return !path.endsWith('/login.php') && !path.endsWith('/pages/login.php');
}

function getApiAuthUrl() {
    const path = window.location.pathname.toLowerCase();
    return path.includes('/pages/') ? '../api/auth.php?action=check' : 'api/auth.php?action=check';
}

function getLoginRedirectUrl() {
    const path = window.location.pathname.toLowerCase();
    return path.includes('/pages/') ? '../login.php' : 'login.php';
}

function getSessionEndedMessage(reason) {
    if (reason === 'force_logout') {
        return 'Your session was ended by an administrator.';
    }

    if (reason === 'blocked') {
        return 'Your account has been blocked.';
    }

    return 'Your session has expired. Please log in again.';
}

function redirectToLoginWithMessage(reason) {
    try {
        sessionStorage.setItem(VENDIX_SESSION_WARNING_KEY, getSessionEndedMessage(reason));
    } catch (error) {
        console.error('Error storing session warning:', error);
    }

    window.location.href = getLoginRedirectUrl();
}

function flushSessionWarning() {
    try {
        const message = sessionStorage.getItem(VENDIX_SESSION_WARNING_KEY);

        if (!message) {
            return;
        }

        sessionStorage.removeItem(VENDIX_SESSION_WARNING_KEY);
        showNotification(message, 'warning', 5000);
    } catch (error) {
        console.error('Error restoring session warning:', error);
    }
}

function handleUnauthorizedApiResponse(response, data) {
    const statusCode = response && typeof response.status === 'number' ? response.status : 0;
    const reason = data && data.reason ? data.reason : null;

    if (statusCode === 401 || reason === 'force_logout' || reason === 'blocked' || reason === 'missing_user' || reason === 'not_logged_in') {
        redirectToLoginWithMessage(reason);
        return true;
    }

    return false;
}

async function checkSessionStatus() {
    try {
        const response = await fetch(getApiAuthUrl(), {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            },
            cache: 'no-store'
        });

        if (!response.ok) {
            return true;
        }

        const data = await response.json();

        if (data && data.status === 'success' && data.logged_in === false) {
            redirectToLoginWithMessage(data.reason);
            return false;
        }

        return true;
    } catch (error) {
        console.error('Error checking session status:', error);
        return true;
    }
}

function startSessionMonitor() {
    flushSessionWarning();

    if (!shouldMonitorSession()) {
        return;
    }

    checkSessionStatus();

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            checkSessionStatus();
        }
    });

    window.addEventListener('focus', checkSessionStatus);

    window.setInterval(() => {
        if (document.visibilityState === 'visible') {
            checkSessionStatus();
        }
    }, VENDIX_SESSION_MONITOR_INTERVAL_MS);
}

function confirmAction(message, options = {}) {
    const overlay = getConfirmDialog();
    const dialog = overlay.querySelector('.vendix-confirm-dialog');
    const messageNode = overlay.querySelector('.vendix-confirm-message');
    const titleNode = overlay.querySelector('.vendix-confirm-title');
    const acceptButton = overlay.querySelector('.vendix-confirm-accept');
    const cancelButton = overlay.querySelector('.vendix-confirm-cancel');
    const acceptLabel = options.acceptLabel || 'Confirm';
    const cancelLabel = options.cancelLabel || 'Cancel';
    const title = options.title || 'Please Confirm';

    titleNode.textContent = title;
    messageNode.textContent = String(message || '');
    acceptButton.textContent = acceptLabel;
    cancelButton.textContent = cancelLabel;

    overlay.classList.add('is-visible');
    document.body.classList.add('vendix-modal-open');

    return new Promise((resolve) => {
        let settled = false;

        const cleanup = (value) => {
            if (settled) {
                return;
            }

            settled = true;
            overlay.classList.remove('is-visible');
            document.body.classList.remove('vendix-modal-open');
            acceptButton.removeEventListener('click', onAccept);
            cancelButton.removeEventListener('click', onCancel);
            overlay.removeEventListener('click', onOverlayClick);
            document.removeEventListener('keydown', onKeyDown);
            resolve(value);
        };

        const onAccept = () => cleanup(true);
        const onCancel = () => cleanup(false);
        const onOverlayClick = (event) => {
            if (event.target === overlay) {
                cleanup(false);
            }
        };
        const onKeyDown = (event) => {
            if (event.key === 'Escape') {
                cleanup(false);
            }
        };

        acceptButton.addEventListener('click', onAccept);
        cancelButton.addEventListener('click', onCancel);
        overlay.addEventListener('click', onOverlayClick);
        document.addEventListener('keydown', onKeyDown);

        requestAnimationFrame(() => {
            dialog.focus();
            acceptButton.focus();
        });
    });
}

window.vendixNotify = showNotification;
window.vendixNotifyAndReload = notifyAndReload;
window.vendixConfirm = confirmAction;
window.vendixHandleUnauthorizedApiResponse = handleUnauthorizedApiResponse;
window.vendixCheckSessionStatus = checkSessionStatus;
window.vendixSyncCsrfFields = syncCsrfFields;
window.alert = function (message) {
    showNotification(message);
};
