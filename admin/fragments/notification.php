<?php
declare(strict_types=1);

/**
 * /admin/fragments/notification.php
 * Production – Notification System Admin Panel (5 tabs)
 */

$isAjax     = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

if ($isFragment) {
    require_once __DIR__ . '/../includes/admin_context.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
}

if (!is_admin_logged_in()) {
    if ($isFragment) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    } else {
        header('Location: /admin/login.php');
        exit;
    }
}

$user     = admin_user();
$lang     = admin_lang();
$dir      = admin_dir();
$csrf     = admin_csrf();
$isPlatformAdmin = function_exists('is_platform_admin') ? is_platform_admin() : false;
$userType        = function_exists('get_user_type')     ? get_user_type()     : 'guest';
$tenantId = admin_tenant_id();

// Permissions
$canCreate   = can_create('notifications')   || can('notifications.manage');
$canViewAll  = can_view_all('notifications')  || is_super_admin();
$canViewOwn  = can_view_own('notifications');
$canEditAll  = can_edit_all('notifications')  || can('notifications.manage');
$canDeleteAll= can_delete_all('notifications')|| can('notifications.manage');
$canView     = $canViewAll || $canViewOwn;
$canEdit     = $canEditAll;
$canDelete   = $canDeleteAll;

if (!$canView && !is_super_admin()) {
    if ($isFragment) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    } else {
        http_response_code(403);
        die('Access denied');
    }
}

function __t($key, $fallback = '') {
    if (function_exists('i18n_get')) {
        $v = i18n_get($key);
        return $v ?? ($fallback ?? $key);
    }
    return $fallback ?? $key;
}

$apiBase = '/api';
?>
<?php if ($isFragment): ?>
<link rel="stylesheet" href="/admin/assets/css/pages/notification.css?v=<?= assetVer() ?>">
<?php endif; ?>

<meta data-page="notifications"
      data-i18n-files="/admin/languages/Notifications/<?= rawurlencode($lang) ?>.json">

<div class="page-container" id="notificationPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="notifications.title"><?= __t('notifications.title', 'Notifications') ?></h1>
            <p class="page-subtitle" data-i18n="notifications.subtitle"><?= __t('notifications.subtitle', 'Manage system notifications and delivery channels') ?></p>
        </div>
        <div class="page-header-actions">
            <?php if ($canCreate): ?>
            <button id="btnAddRecord" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                <span id="btnAddLabel"><?= __t('types.add_new', 'Add') ?></span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($isPlatformAdmin): ?>
    <!-- ═══ PLATFORM ADMIN — TENANT SELECTOR ═══ -->
    <div class="card platform-admin-panel" id="notifPlatformAdminPanel">
        <div class="card-header" style="background:var(--color-warning,#ff9800);color:#fff;display:flex;align-items:center;gap:8px">
            <i class="fas fa-shield-alt"></i>
            <strong><?= __t('platform_admin.panel_title', 'Platform Admin — Tenant Context') ?></strong>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label><?= __t('platform_admin.search_user', 'Search User (ID or name)') ?></label>
                    <div style="display:flex;gap:6px">
                        <input type="text" id="notifPaUserSearch" class="form-control"
                               placeholder="<?= __t('platform_admin.search_placeholder', 'User ID or name...') ?>">
                        <button type="button" id="notifPaUserSearchBtn" class="btn btn-secondary btn-sm">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                    <div id="notifPaUserSearchResults" class="pa-search-results" style="display:none"></div>
                </div>
                <div class="form-group">
                    <label><?= __t('platform_admin.select_tenant', 'Select Tenant') ?></label>
                    <select id="notifPaTenantSelect" class="form-control">
                        <option value=""><?= __t('platform_admin.select_tenant_placeholder', '-- Select tenant --') ?></option>
                    </select>
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end">
                    <button type="button" id="notifPaApplyTenantBtn" class="btn btn-warning btn-sm" disabled>
                        <i class="fas fa-user-shield"></i>
                        <?= __t('platform_admin.act_on_behalf', 'Act on Behalf') ?>
                    </button>
                </div>
            </div>
            <div id="notifPaActiveTenantBanner" class="pa-active-banner" style="display:none">
                <i class="fas fa-exclamation-triangle"></i>
                <span id="notifPaActiveTenantLabel"></span>
                <button type="button" id="notifPaClearTenantBtn" class="btn btn-sm btn-outline-danger" style="margin-left:auto">
                    <i class="fas fa-times"></i> <?= __t('platform_admin.clear_context', 'Clear') ?>
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="notif-tabs">
        <button class="notif-tab-btn active" data-tab="types">
            <i class="fas fa-tags"></i>
            <span data-i18n="notifications.tabs.types"><?= __t('notifications.tabs.types', 'Types') ?></span>
        </button>
        <button class="notif-tab-btn" data-tab="list">
            <i class="fas fa-bell"></i>
            <span data-i18n="notifications.tabs.list"><?= __t('notifications.tabs.list', 'Notifications') ?></span>
        </button>
        <button class="notif-tab-btn" data-tab="channels">
            <i class="fas fa-broadcast-tower"></i>
            <span data-i18n="notifications.tabs.channels"><?= __t('notifications.tabs.channels', 'Channels') ?></span>
        </button>
        <button class="notif-tab-btn" data-tab="counters">
            <i class="fas fa-tally"></i>
            <span data-i18n="notifications.tabs.counters"><?= __t('notifications.tabs.counters', 'Counters') ?></span>
        </button>
        <button class="notif-tab-btn" data-tab="deliveries">
            <i class="fas fa-paper-plane"></i>
            <span data-i18n="notifications.tabs.deliveries"><?= __t('notifications.tabs.deliveries', 'Deliveries') ?></span>
        </button>
        <button class="notif-tab-btn" data-tab="devices">
            <i class="fas fa-mobile-alt"></i>
            <span data-i18n="notifications.tabs.devices"><?= __t('notifications.tabs.devices', 'Devices') ?></span>
        </button>
        <button class="notif-tab-btn" data-tab="recipients">
            <i class="fas fa-inbox"></i>
            <span data-i18n="notifications.tabs.recipients"><?= __t('notifications.tabs.recipients', 'Recipients') ?></span>
        </button>
        <?php if ($canCreate): ?>
        <button class="notif-tab-btn" data-tab="bulk_send">
            <i class="fas fa-paper-plane"></i>
            <span data-i18n="notifications.tabs.bulk_send"><?= __t('notifications.tabs.bulk_send', 'Bulk Send') ?></span>
        </button>
        <?php endif; ?>
    </div>

    <!-- ── FORM CONTAINER (shared, reused per tab) ── -->
    <div id="notifFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="notifFormTitle"><?= __t('form.add_title', 'Add Record') ?></h3>
            <button type="button" class="btn btn-sm btn-secondary" id="btnCloseForm">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="notifForm" novalidate>
                <input type="hidden" id="formId"     name="id">
                <input type="hidden" id="formTab"    name="_tab">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                <!-- ── TYPES fields ── -->
                <div class="notif-form-section" data-form-tab="types">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fCode" class="required" data-i18n="form.fields.code.label"><?= __t('form.fields.code.label','Code') ?></label>
                            <input type="text" id="fCode" name="code" class="form-control"
                                   placeholder="<?= __t('form.fields.code.placeholder','Enter code') ?>"
                                   data-i18n-placeholder="form.fields.code.placeholder">
                        </div>
                        <div class="form-group">
                            <label for="fName" class="required" data-i18n="form.fields.name.label"><?= __t('form.fields.name.label','Name') ?></label>
                            <input type="text" id="fName" name="name" class="form-control"
                                   placeholder="<?= __t('form.fields.name.placeholder','Enter name') ?>"
                                   data-i18n-placeholder="form.fields.name.placeholder">
                        </div>
                        <div class="form-group">
                            <label for="fIsActive" data-i18n="form.fields.is_active.label"><?= __t('form.fields.is_active.label','Status') ?></label>
                            <select id="fIsActive" name="is_active" class="form-control">
                                <option value="1" data-i18n="form.fields.is_active.active"><?= __t('form.fields.is_active.active','Active') ?></option>
                                <option value="0" data-i18n="form.fields.is_active.inactive"><?= __t('form.fields.is_active.inactive','Inactive') ?></option>
                            </select>
                        </div>
                        <?php if ($isPlatformAdmin): ?>
                        <div class="form-group">
                            <label for="fOwnerScope" data-i18n="form.fields.owner_scope.label"><?= __t('form.fields.owner_scope.label','Owner Scope') ?></label>
                            <select id="fOwnerScope" name="owner_scope" class="form-control">
                                <option value="shared" data-i18n="form.fields.owner_scope.shared"><?= __t('form.fields.owner_scope.shared','Shared') ?></option>
                                <option value="tenant" data-i18n="form.fields.owner_scope.tenant"><?= __t('form.fields.owner_scope.tenant','Tenant') ?></option>
                                <option value="platform" data-i18n="form.fields.owner_scope.platform"><?= __t('form.fields.owner_scope.platform','Platform') ?></option>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="fDescription" data-i18n="form.fields.description.label"><?= __t('form.fields.description.label','Description') ?></label>
                        <textarea id="fDescription" name="description" class="form-control" rows="2"
                                  placeholder="<?= __t('form.fields.description.placeholder','Enter description') ?>"
                                  data-i18n-placeholder="form.fields.description.placeholder"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="fDefaultTemplate" data-i18n="form.fields.default_template.label"><?= __t('form.fields.default_template.label','Default Template') ?></label>
                        <textarea id="fDefaultTemplate" name="default_template" class="form-control" rows="4"
                                  placeholder="<?= __t('form.fields.default_template.placeholder','Enter default template') ?>"
                                  data-i18n-placeholder="form.fields.default_template.placeholder"></textarea>
                    </div>
                </div>

                <!-- ── NOTIFICATIONS fields ── -->
                <div class="notif-form-section" data-form-tab="list" style="display:none">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fTenantId" class="required" data-i18n="form.fields.tenant_id.label"><?= __t('form.fields.tenant_id.label','Tenant ID') ?></label>
                            <input type="number" id="fTenantId" name="tenant_id" class="form-control"
                                   value="<?= $tenantId ?>" <?= is_super_admin() ? '' : 'readonly' ?>
                                   data-lookup="tenant">
                            <span class="id-lookup-hint" data-for="fTenantId"></span>
                        </div>
                        <div class="form-group">
                            <label for="fNotifTypeId" data-i18n="form.fields.notification_type_id.label"><?= __t('form.fields.notification_type_id.label','Notification Type') ?></label>
                            <select id="fNotifTypeId" name="notification_type_id" class="form-control">
                                <option value="">-- Select Type --</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="fPriority" data-i18n="form.fields.priority.label"><?= __t('form.fields.priority.label','Priority') ?></label>
                            <select id="fPriority" name="priority" class="form-control">
                                <option value="low"    data-i18n="form.fields.priority.low"><?= __t('form.fields.priority.low','Low') ?></option>
                                <option value="normal" data-i18n="form.fields.priority.normal" selected><?= __t('form.fields.priority.normal','Normal') ?></option>
                                <option value="high"   data-i18n="form.fields.priority.high"><?= __t('form.fields.priority.high','High') ?></option>
                                <option value="urgent" data-i18n="form.fields.priority.urgent"><?= __t('form.fields.priority.urgent','Urgent') ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fSenderEntityId" data-i18n="form.fields.sender_entity_id.label"><?= __t('form.fields.sender_entity_id.label','Sender Entity ID') ?></label>
                            <input type="number" id="fSenderEntityId" name="sender_entity_id" class="form-control"
                                   placeholder="<?= __t('form.fields.sender_entity_id.placeholder','Optional') ?>"
                                   data-lookup="entity">
                            <span class="id-lookup-hint" data-for="fSenderEntityId"></span>
                        </div>
                        <div class="form-group">
                            <label for="fEntityId" data-i18n="form.fields.entity_id.label"><?= __t('form.fields.entity_id.label','Entity ID') ?></label>
                            <input type="number" id="fEntityId" name="entity_id" class="form-control"
                                   placeholder="<?= __t('form.fields.entity_id.placeholder','Optional') ?>"
                                   data-lookup="entity">
                            <span class="id-lookup-hint" data-for="fEntityId"></span>
                        </div>
                        <div class="form-group">
                            <label for="fExpiresAt" data-i18n="form.fields.expires_at.label"><?= __t('form.fields.expires_at.label','Expires At') ?></label>
                            <input type="datetime-local" id="fExpiresAt" name="expires_at" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="fTitle" class="required" data-i18n="form.fields.title.label"><?= __t('form.fields.title.label','Title') ?></label>
                        <input type="text" id="fTitle" name="title" class="form-control"
                               placeholder="<?= __t('form.fields.title.placeholder','Enter title') ?>">
                    </div>
                    <div class="form-group">
                        <label for="fMessage" class="required" data-i18n="form.fields.message.label"><?= __t('form.fields.message.label','Message') ?></label>
                        <textarea id="fMessage" name="message" class="form-control" rows="3"
                                  placeholder="<?= __t('form.fields.message.placeholder','Enter message') ?>"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="fData" data-i18n="form.fields.data.label"><?= __t('form.fields.data.label','Data (JSON)') ?></label>
                        <textarea id="fData" name="data" class="form-control" rows="2"
                                  placeholder='{"key": "value"}'></textarea>
                    </div>
                    <div class="form-group notif-channels-group">
                        <label data-i18n="form.fields.channels.label"><?= __t('form.fields.channels.label','Delivery Channels') ?></label>
                        <div class="notif-channels-checkboxes">
                            <label class="notif-channel-check">
                                <input type="checkbox" name="channels[]" value="database" checked>
                                <span><i class="fas fa-database"></i> <span data-i18n="form.fields.channels.database"><?= __t('form.fields.channels.database','Database') ?></span></span>
                            </label>
                            <label class="notif-channel-check">
                                <input type="checkbox" name="channels[]" value="push" id="chkPushChannel">
                                <span><i class="fab fa-firebase"></i> <span data-i18n="form.fields.channels.push"><?= __t('form.fields.channels.push','Push (Firebase)') ?></span></span>
                            </label>
                            <label class="notif-channel-check">
                                <input type="checkbox" name="channels[]" value="email">
                                <span><i class="fas fa-envelope"></i> <span data-i18n="form.fields.channels.email"><?= __t('form.fields.channels.email','Email') ?></span></span>
                            </label>
                            <label class="notif-channel-check">
                                <input type="checkbox" name="channels[]" value="sms">
                                <span><i class="fas fa-sms"></i> <span data-i18n="form.fields.channels.sms"><?= __t('form.fields.channels.sms','SMS') ?></span></span>
                            </label>
                        </div>
                    </div>
                    <!-- ── Device Picker (shown when push channel is checked) ── -->
                    <div class="form-group notif-device-picker-group" id="devicePickerGroup" style="display:none">
                        <label><i class="fas fa-mobile-alt"></i> <span data-i18n="send_notification.target_devices"><?= __t('send_notification.target_devices','Target Devices') ?></span></label>
                        <p class="device-picker-hint" data-i18n="send_notification.device_picker_hint"><?= __t('send_notification.device_picker_hint','Select specific devices or leave all unchecked to send to all devices.') ?></p>
                        <div id="devicePickerList" class="device-picker-list">
                            <p class="device-picker-empty" data-i18n="send_notification.enter_recipient_first"><?= __t('send_notification.enter_recipient_first','Enter a Recipient ID first to load devices.') ?></p>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary" id="btnLoadDevices" style="margin-top:6px">
                            <i class="fas fa-sync-alt"></i>
                            <span data-i18n="send_notification.load_devices"><?= __t('send_notification.load_devices','Load Devices') ?></span>
                        </button>
                    </div>
                    <div class="form-group notif-recipient-group">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="fSendRecipientType" class="required" data-i18n="send_notification.recipient_type"><?= __t('send_notification.recipient_type','Recipient Type') ?></label>
                                <select id="fSendRecipientType" name="send_recipient_type" class="form-control">
                                    <option value="user"><?= __t('form.fields.recipient_type.user','User') ?></option>
                                    <option value="entity"><?= __t('form.fields.recipient_type.entity','Entity') ?></option>
                                    <option value="tenant"><?= __t('form.fields.recipient_type.tenant','Tenant') ?></option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="fSendRecipientId" class="required" data-i18n="send_notification.recipient_id"><?= __t('send_notification.recipient_id','Recipient ID') ?></label>
                                <input type="number" id="fSendRecipientId" name="send_recipient_id" class="form-control"
                                       placeholder="<?= __t('form.fields.recipient_id.placeholder','Enter ID') ?>"
                                       data-lookup="recipient_send">
                                <span class="id-lookup-hint" data-for="fSendRecipientId"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── CHANNELS fields ── -->
                <div class="notif-form-section" data-form-tab="channels" style="display:none">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fChCode" class="required" data-i18n="form.fields.code.label"><?= __t('form.fields.code.label','Code') ?></label>
                            <input type="text" id="fChCode" name="code" class="form-control"
                                   placeholder="<?= __t('form.fields.code.placeholder','Enter code') ?>">
                        </div>
                        <div class="form-group">
                            <label for="fChName" class="required" data-i18n="form.fields.name.label"><?= __t('form.fields.name.label','Name') ?></label>
                            <input type="text" id="fChName" name="name" class="form-control"
                                   placeholder="<?= __t('form.fields.name.placeholder','Enter name') ?>">
                        </div>
                        <div class="form-group">
                            <label for="fChIsActive" data-i18n="form.fields.is_active.label"><?= __t('form.fields.is_active.label','Status') ?></label>
                            <select id="fChIsActive" name="is_active" class="form-control">
                                <option value="1"><?= __t('form.fields.is_active.active','Active') ?></option>
                                <option value="0"><?= __t('form.fields.is_active.inactive','Inactive') ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ── COUNTERS fields ── -->
                <div class="notif-form-section" data-form-tab="counters" style="display:none">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fCtrTenantId" class="required" data-i18n="form.fields.tenant_id.label"><?= __t('form.fields.tenant_id.label','Tenant ID') ?></label>
                            <input type="number" id="fCtrTenantId" name="tenant_id" class="form-control"
                                   value="<?= $tenantId ?>" <?= is_super_admin() ? '' : 'readonly' ?>
                                   data-lookup="tenant">
                            <span class="id-lookup-hint" data-for="fCtrTenantId"></span>
                        </div>
                        <div class="form-group">
                            <label for="fRecipientType" class="required" data-i18n="form.fields.recipient_type.label"><?= __t('form.fields.recipient_type.label','Recipient Type') ?></label>
                            <select id="fRecipientType" name="recipient_type" class="form-control">
                                <option value="user"   data-i18n="form.fields.recipient_type.user"><?= __t('form.fields.recipient_type.user','User') ?></option>
                                <option value="entity" data-i18n="form.fields.recipient_type.entity"><?= __t('form.fields.recipient_type.entity','Entity') ?></option>
                                <option value="tenant" data-i18n="form.fields.recipient_type.tenant"><?= __t('form.fields.recipient_type.tenant','Tenant') ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="fRecipientId" class="required" data-i18n="form.fields.recipient_id.label"><?= __t('form.fields.recipient_id.label','Recipient ID') ?></label>
                            <input type="number" id="fRecipientId" name="recipient_id" class="form-control"
                                   placeholder="<?= __t('form.fields.recipient_id.placeholder','Enter ID') ?>"
                                   data-lookup="recipient">
                            <span class="id-lookup-hint" data-for="fRecipientId"></span>
                        </div>
                        <div class="form-group">
                            <label for="fUnreadCount" data-i18n="form.fields.unread_count.label"><?= __t('form.fields.unread_count.label','Unread Count') ?></label>
                            <input type="number" id="fUnreadCount" name="unread_count" class="form-control" value="0" min="0">
                        </div>
                    </div>
                </div>

                <!-- ── DELIVERIES fields ── -->
                <div class="notif-form-section" data-form-tab="deliveries" style="display:none">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fNotifId" class="required" data-i18n="form.fields.notification_id.label"><?= __t('form.fields.notification_id.label','Notification ID') ?></label>
                            <input type="number" id="fNotifId" name="notification_id" class="form-control"
                                   data-lookup="notification">
                            <span class="id-lookup-hint" data-for="fNotifId"></span>
                        </div>
                        <div class="form-group">
                            <label for="fChannelId" class="required" data-i18n="form.fields.channel_id.label"><?= __t('form.fields.channel_id.label','Channel') ?></label>
                            <select id="fChannelId" name="channel_id" class="form-control">
                                <option value="">-- Select Channel --</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="fDeliveryStatus" data-i18n="form.fields.delivery_status.label"><?= __t('form.fields.delivery_status.label','Delivery Status') ?></label>
                            <select id="fDeliveryStatus" name="delivery_status" class="form-control">
                                <option value="pending" data-i18n="form.fields.delivery_status.pending"><?= __t('form.fields.delivery_status.pending','Pending') ?></option>
                                <option value="sent"    data-i18n="form.fields.delivery_status.sent"><?= __t('form.fields.delivery_status.sent','Sent') ?></option>
                                <option value="failed"  data-i18n="form.fields.delivery_status.failed"><?= __t('form.fields.delivery_status.failed','Failed') ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fAttempts" data-i18n="form.fields.attempts.label"><?= __t('form.fields.attempts.label','Attempts') ?></label>
                            <input type="number" id="fAttempts" name="attempts" class="form-control" value="0" min="0">
                        </div>
                        <div class="form-group">
                            <label for="fSentAt" data-i18n="form.fields.sent_at.label"><?= __t('form.fields.sent_at.label','Sent At') ?></label>
                            <input type="datetime-local" id="fSentAt" name="sent_at" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="fErrorMessage" data-i18n="form.fields.error_message.label"><?= __t('form.fields.error_message.label','Error Message') ?></label>
                        <textarea id="fErrorMessage" name="error_message" class="form-control" rows="2"
                                  placeholder="<?= __t('form.fields.error_message.placeholder','Error message if any') ?>"></textarea>
                    </div>
                </div>

                <!-- ── DEVICES fields ── -->
                <div class="notif-form-section" data-form-tab="devices" style="display:none">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fDevUserId" class="required" data-i18n="form.fields.user_id.label"><?= __t('form.fields.user_id.label','User ID') ?></label>
                            <input type="number" id="fDevUserId" name="user_id" class="form-control"
                                   placeholder="<?= __t('form.fields.user_id.placeholder','Enter user ID') ?>"
                                   data-lookup="device_user">
                            <span class="id-lookup-hint" data-for="fDevUserId"></span>
                        </div>
                        <div class="form-group">
                            <label for="fDeviceType" data-i18n="form.fields.device_type.label"><?= __t('form.fields.device_type.label','Device Type') ?></label>
                            <select id="fDeviceType" name="device_type" class="form-control">
                                <option value="web">Web</option>
                                <option value="android">Android</option>
                                <option value="ios">iOS</option>
                                <option value="mobile">Mobile</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="fDevIsActive" data-i18n="form.fields.is_active.label"><?= __t('form.fields.is_active.label','Status') ?></label>
                            <select id="fDevIsActive" name="is_active" class="form-control">
                                <option value="1"><?= __t('form.fields.is_active.active','Active') ?></option>
                                <option value="0"><?= __t('form.fields.is_active.inactive','Inactive') ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="fFcmToken" class="required" data-i18n="form.fields.fcm_token.label"><?= __t('form.fields.fcm_token.label','FCM Token') ?></label>
                        <textarea id="fFcmToken" name="fcm_token" class="form-control" rows="2"
                                  placeholder="<?= __t('form.fields.fcm_token.placeholder','Firebase Cloud Messaging token') ?>"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="fDeviceName" data-i18n="form.fields.device_name.label"><?= __t('form.fields.device_name.label','Device Name') ?></label>
                        <input type="text" id="fDeviceName" name="device_name" class="form-control"
                               placeholder="<?= __t('form.fields.device_name.placeholder','Enter device name') ?>">
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="btnSubmitForm">
                        <i class="fas fa-save"></i>
                        <span data-i18n="form.buttons.save"><?= __t('form.buttons.save','Save') ?></span>
                    </button>
                    <?php if ($canCreate): ?>
                    <button type="button" class="btn btn-success" id="btnSendNotification" style="display:none">
                        <i class="fas fa-paper-plane"></i>
                        <span data-i18n="form.buttons.send"><?= __t('form.buttons.send','Send Notification') ?></span>
                    </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-secondary" id="btnCancelForm" data-i18n="form.buttons.cancel">
                        <?= __t('form.buttons.cancel','Cancel') ?>
                    </button>
                    <?php if ($canDelete): ?>
                    <button type="button" id="btnDeleteRecord" class="btn btn-danger" style="display:none">
                        <i class="fas fa-trash"></i>
                        <span data-i18n="table.actions.delete"><?= __t('table.actions.delete','Delete') ?></span>
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- ── BULK SEND PANEL ── -->
    <?php if ($canCreate): ?>
    <div id="bulkSendPanel" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-paper-plane"></i> <span data-i18n="bulk_send.title"><?= __t('bulk_send.title', 'Bulk Send Notification') ?></span></h3>
        </div>
        <div class="card-body">
            <!-- Notification Content -->
            <div class="bulk-send-content">
                <div class="form-row">
                    <div class="form-group">
                        <label for="bsTenantId" class="required"><?= __t('form.fields.tenant_id.label','Tenant ID') ?></label>
                        <input type="number" id="bsTenantId" class="form-control"
                               value="<?= $tenantId ?>" <?= is_super_admin() ? '' : 'readonly' ?>>
                    </div>
                    <div class="form-group">
                        <label for="bsTypeCode"><?= __t('form.fields.notification_type_id.label','Notification Type') ?></label>
                        <select id="bsTypeCode" class="form-control">
                            <option value="general">General</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="bsPriority"><?= __t('form.fields.priority.label','Priority') ?></label>
                        <select id="bsPriority" class="form-control">
                            <option value="low"><?= __t('form.fields.priority.low','Low') ?></option>
                            <option value="normal" selected><?= __t('form.fields.priority.normal','Normal') ?></option>
                            <option value="high"><?= __t('form.fields.priority.high','High') ?></option>
                            <option value="urgent"><?= __t('form.fields.priority.urgent','Urgent') ?></option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="bsTitle" class="required"><?= __t('form.fields.title.label','Title') ?></label>
                    <input type="text" id="bsTitle" class="form-control" placeholder="<?= __t('form.fields.title.placeholder','Enter title') ?>">
                </div>
                <div class="form-group">
                    <label for="bsMessage" class="required"><?= __t('form.fields.message.label','Message') ?></label>
                    <textarea id="bsMessage" class="form-control" rows="3" placeholder="<?= __t('form.fields.message.placeholder','Enter message') ?>"></textarea>
                </div>
                <div class="form-group notif-channels-group">
                    <label><?= __t('form.fields.channels.label','Delivery Channels') ?></label>
                    <div class="notif-channels-checkboxes">
                        <label class="notif-channel-check">
                            <input type="checkbox" name="bs_channels[]" value="database" checked>
                            <span><i class="fas fa-database"></i> <?= __t('form.fields.channels.database','Database') ?></span>
                        </label>
                        <label class="notif-channel-check">
                            <input type="checkbox" name="bs_channels[]" value="push">
                            <span><i class="fab fa-firebase"></i> <?= __t('form.fields.channels.push','Push (Firebase)') ?></span>
                        </label>
                        <label class="notif-channel-check">
                            <input type="checkbox" name="bs_channels[]" value="email">
                            <span><i class="fas fa-envelope"></i> <?= __t('form.fields.channels.email','Email') ?></span>
                        </label>
                        <label class="notif-channel-check">
                            <input type="checkbox" name="bs_channels[]" value="sms">
                            <span><i class="fas fa-sms"></i> <?= __t('form.fields.channels.sms','SMS') ?></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- User Browser / Recipient Selection -->
            <div class="bulk-send-recipients">
                <h4><i class="fas fa-users"></i> <span data-i18n="bulk_send.select_recipients"><?= __t('bulk_send.select_recipients', 'Select Recipients') ?></span></h4>

                <!-- Filters -->
                <div class="bulk-send-filters">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="bsFilterSearch"><?= __t('filters.search','Search') ?></label>
                            <input type="text" id="bsFilterSearch" class="form-control" placeholder="<?= __t('bulk_send.search_users_placeholder','Search by username or email...') ?>">
                        </div>
                        <div class="form-group">
                            <label for="bsFilterRole"><?= __t('bulk_send.filter_role','Role') ?></label>
                            <select id="bsFilterRole" class="form-control">
                                <option value=""><?= __t('bulk_send.all_roles','All Roles') ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="bsFilterActive"><?= __t('filters.status','Status') ?></label>
                            <select id="bsFilterActive" class="form-control">
                                <option value=""><?= __t('filters.status_options.all','All') ?></option>
                                <option value="1" selected><?= __t('filters.status_options.active','Active') ?></option>
                                <option value="0"><?= __t('filters.status_options.inactive','Inactive') ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="bsFilterDeviceType"><?= __t('bulk_send.has_device','Device Type') ?></label>
                            <select id="bsFilterDeviceType" class="form-control">
                                <option value=""><?= __t('bulk_send.any_device','Any') ?></option>
                                <option value="web">Web</option>
                                <option value="android">Android</option>
                                <option value="ios">iOS</option>
                            </select>
                        </div>
                    </div>
                    <div class="bulk-send-filter-actions">
                        <button type="button" class="btn btn-sm btn-primary" id="btnBsLoadUsers">
                            <i class="fas fa-search"></i> <span data-i18n="bulk_send.load_users"><?= __t('bulk_send.load_users','Load Users') ?></span>
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary" id="btnBsSelectAll">
                            <i class="fas fa-check-double"></i> <span data-i18n="bulk_send.select_all"><?= __t('bulk_send.select_all','Select All') ?></span>
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary" id="btnBsDeselectAll">
                            <i class="fas fa-times"></i> <span data-i18n="bulk_send.deselect_all"><?= __t('bulk_send.deselect_all','Deselect All') ?></span>
                        </button>
                        <span class="bulk-send-count" id="bsSelectedCount">0 <?= __t('bulk_send.selected','selected') ?></span>
                    </div>
                </div>

                <!-- User List -->
                <div id="bsUserList" class="bulk-send-user-list">
                    <p class="bulk-send-empty"><?= __t('bulk_send.click_load','Click "Load Users" to browse recipients.') ?></p>
                </div>

                <!-- Pagination -->
                <div id="bsUserPagination" class="bulk-send-pagination" style="display:none"></div>
            </div>

            <!-- Send Button -->
            <div class="bulk-send-actions">
                <button type="button" class="btn btn-success btn-lg" id="btnBulkSend">
                    <i class="fas fa-paper-plane"></i>
                    <span data-i18n="bulk_send.send_button"><?= __t('bulk_send.send_button','Send to Selected Recipients') ?></span>
                    (<span id="bsSendCount">0</span>)
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── FILTER CARD ── -->
    <div class="card filter-card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="searchInput" data-i18n="filters.search"><?= __t('filters.search','Search') ?></label>
                    <input type="text" id="searchInput" class="form-control"
                           placeholder="<?= __t('filters.search_placeholder','Search...') ?>"
                           data-i18n-placeholder="filters.search_placeholder">
                </div>
                <!-- Status filter (Types + Channels) -->
                <div class="filter-group" id="filterStatusGroup">
                    <label for="statusFilter" data-i18n="filters.status"><?= __t('filters.status','Status') ?></label>
                    <select id="statusFilter" class="form-control">
                        <option value="" data-i18n="filters.status_options.all"><?= __t('filters.status_options.all','All Status') ?></option>
                        <option value="1" data-i18n="filters.status_options.active"><?= __t('filters.status_options.active','Active') ?></option>
                        <option value="0" data-i18n="filters.status_options.inactive"><?= __t('filters.status_options.inactive','Inactive') ?></option>
                    </select>
                </div>
                <!-- Priority filter (Notifications) -->
                <div class="filter-group" id="filterPriorityGroup" style="display:none">
                    <label for="priorityFilter" data-i18n="filters.priority"><?= __t('filters.priority','Priority') ?></label>
                    <select id="priorityFilter" class="form-control">
                        <option value="" data-i18n="filters.priority_options.all"><?= __t('filters.priority_options.all','All Priorities') ?></option>
                        <option value="low"    data-i18n="filters.priority_options.low"><?= __t('filters.priority_options.low','Low') ?></option>
                        <option value="normal" data-i18n="filters.priority_options.normal"><?= __t('filters.priority_options.normal','Normal') ?></option>
                        <option value="high"   data-i18n="filters.priority_options.high"><?= __t('filters.priority_options.high','High') ?></option>
                        <option value="urgent" data-i18n="filters.priority_options.urgent"><?= __t('filters.priority_options.urgent','Urgent') ?></option>
                    </select>
                </div>
                <!-- Delivery status filter (Deliveries) -->
                <div class="filter-group" id="filterDeliveryStatusGroup" style="display:none">
                    <label for="deliveryStatusFilter" data-i18n="filters.delivery_status"><?= __t('filters.delivery_status','Delivery Status') ?></label>
                    <select id="deliveryStatusFilter" class="form-control">
                        <option value="" data-i18n="filters.delivery_status_options.all"><?= __t('filters.delivery_status_options.all','All') ?></option>
                        <option value="pending" data-i18n="filters.delivery_status_options.pending"><?= __t('filters.delivery_status_options.pending','Pending') ?></option>
                        <option value="sent"    data-i18n="filters.delivery_status_options.sent"><?= __t('filters.delivery_status_options.sent','Sent') ?></option>
                        <option value="failed"  data-i18n="filters.delivery_status_options.failed"><?= __t('filters.delivery_status_options.failed','Failed') ?></option>
                    </select>
                </div>
                <!-- Recipient type filter (Counters) -->
                <div class="filter-group" id="filterRecipientTypeGroup" style="display:none">
                    <label for="recipientTypeFilter" data-i18n="filters.recipient_type"><?= __t('filters.recipient_type','Recipient Type') ?></label>
                    <select id="recipientTypeFilter" class="form-control">
                        <option value="" data-i18n="filters.recipient_type_options.all"><?= __t('filters.recipient_type_options.all','All') ?></option>
                        <option value="user"   data-i18n="filters.recipient_type_options.user"><?= __t('filters.recipient_type_options.user','User') ?></option>
                        <option value="entity" data-i18n="filters.recipient_type_options.entity"><?= __t('filters.recipient_type_options.entity','Entity') ?></option>
                        <option value="tenant" data-i18n="filters.recipient_type_options.tenant"><?= __t('filters.recipient_type_options.tenant','Tenant') ?></option>
                    </select>
                </div>
                <?php if (is_super_admin()): ?>
                <div class="filter-group" id="filterTenantGroup">
                    <label for="tenantFilter" data-i18n="filters.tenant_id"><?= __t('filters.tenant_id','Tenant') ?></label>
                    <input type="number" id="tenantFilter" class="form-control"
                           value="<?= $tenantId ?>"
                           placeholder="<?= __t('filters.tenant_placeholder','Filter by tenant') ?>">
                </div>
                <?php endif; ?>
                <?php if ($isPlatformAdmin): ?>
                <div class="filter-group" id="filterOwnerScopeGroup" style="display:none">
                    <label for="ownerScopeFilter" data-i18n="filters.owner_scope"><?= __t('filters.owner_scope','Owner Scope') ?></label>
                    <select id="ownerScopeFilter" class="form-control">
                        <option value=""><?= __t('filters.owner_scope_options.all','All Scopes') ?></option>
                        <option value="platform"><?= __t('form.fields.owner_scope.platform','Platform') ?></option>
                        <option value="tenant"><?= __t('form.fields.owner_scope.tenant','Tenant') ?></option>
                        <option value="shared"><?= __t('form.fields.owner_scope.shared','Shared') ?></option>
                    </select>
                </div>
                <?php endif; ?>
                <!-- Device type filter (Devices) -->
                <div class="filter-group" id="filterDeviceTypeGroup" style="display:none">
                    <label for="deviceTypeFilter"><?= __t('form.fields.device_type.label','Device Type') ?></label>
                    <select id="deviceTypeFilter" class="form-control">
                        <option value="">All</option>
                        <option value="web">Web</option>
                        <option value="android">Android</option>
                        <option value="ios">iOS</option>
                        <option value="mobile">Mobile</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button id="btnApplyFilters" class="btn btn-primary" data-i18n="filters.apply"><?= __t('filters.apply','Apply') ?></button>
                    <button id="btnResetFilters" class="btn btn-secondary"    data-i18n="filters.reset"><?= __t('filters.reset','Reset') ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Results count -->
    <div id="resultsCount" class="results-count" style="display:none">
        <span><i class="fas fa-list"></i> <span id="resultsCountText"></span></span>
    </div>

    <!-- Table card -->
    <div class="card table-card">
        <div class="card-body">
            <div id="tableLoading" class="loading-state">
                <div class="spinner"></div>
                <p data-i18n="notifications.loading"><?= __t('notifications.loading','Loading...') ?></p>
            </div>
            <div id="tableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table" id="notifTable">
                        <thead><tr id="tableHead"></tr></thead>
                        <tbody id="tableBody"></tbody>
                    </table>
                </div>
                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        <span data-i18n="pagination.showing"><?= __t('pagination.showing','Showing') ?></span>
                        <span id="paginationInfo">0-0 of 0</span>
                    </div>
                    <div class="pagination" id="pagination"></div>
                </div>
            </div>
            <div id="emptyState" class="empty-state" style="display:none">
                <div class="empty-icon">🔔</div>
                <h3 id="emptyTitle"></h3>
                <p id="emptyMessage"></p>
                <?php if ($canCreate): ?>
                <button class="btn btn-primary" onclick="window.Notifications && window.Notifications.add()">
                    <i class="fas fa-plus"></i> <span id="emptyAddLabel"></span>
                </button>
                <?php endif; ?>
            </div>
            <div id="errorState" class="error-state" style="display:none">
                <div class="error-icon">⚠️</div>
                <h3 data-i18n="messages.error.load_failed"><?= __t('messages.error.load_failed','Error Loading Data') ?></h3>
                <p id="errorMessage"></p>
                <button id="btnRetry" class="btn btn-secondary" data-i18n="notifications.retry"><?= __t('notifications.retry','Retry') ?></button>
            </div>
        </div>
    </div>

</div>

<!-- Global config -->
<script type="text/javascript">
window.APP_CONFIG = window.APP_CONFIG || {};
window.APP_CONFIG.API_BASE   = window.APP_CONFIG.API_BASE   || '<?= $apiBase ?>';
window.APP_CONFIG.TENANT_ID  = window.APP_CONFIG.TENANT_ID  || <?= $tenantId ?>;
window.APP_CONFIG.CSRF_TOKEN = window.APP_CONFIG.CSRF_TOKEN || '<?= addslashes($csrf) ?>';
window.APP_CONFIG.USER_ID    = window.APP_CONFIG.USER_ID    || <?= admin_user_id() ?>;
window.USER_LANGUAGE   = window.USER_LANGUAGE   || '<?= addslashes($lang) ?>';
window.USER_DIRECTION  = window.USER_DIRECTION  || '<?= addslashes($dir) ?>';
window.CSRF_TOKEN      = window.CSRF_TOKEN      || '<?= addslashes($csrf) ?>';
window.PAGE_PERMISSIONS = <?= json_encode([
    'canCreate'        => $canCreate,
    'canEdit'          => $canEdit,
    'canDelete'        => $canDelete,
    'canViewAll'       => $canViewAll,
    'canEditAll'       => $canEditAll,
    'isSuperAdmin'     => is_super_admin(),
    'isPlatformAdmin'  => $isPlatformAdmin,
], JSON_UNESCAPED_UNICODE) ?>;
window.NOTIFICATIONS_CONFIG = {
    api: {
        types:     '<?= $apiBase ?>/notification_types',
        list:      '<?= $apiBase ?>/notifications',
        channels:  '<?= $apiBase ?>/notification_channels',
        counters:  '<?= $apiBase ?>/notification_counters',
        deliveries:'<?= $apiBase ?>/notification_deliveries',
        devices:   '<?= $apiBase ?>/user_devices',
        recipients:'<?= $apiBase ?>/notification_recipients',
        send:      '<?= $apiBase ?>/notifications/send',
        sendBulk:  '<?= $apiBase ?>/notifications/send-bulk'
    },
    tenantId:        <?= $tenantId ?>,
    csrfToken:       '<?= addslashes($csrf) ?>',
    lang:            '<?= addslashes($lang) ?>',
    itemsPerPage:    25,
    isPlatformAdmin: <?= $isPlatformAdmin ? 'true' : 'false' ?>,
};
</script>

<!-- Translation loader -->
<script type="text/javascript">
(function(){
    async function applyTranslations() {
        try {
            const lang = window.USER_LANGUAGE || 'en';
            const url  = `/languages/Notifications/${encodeURIComponent(lang)}.json`;
            const res  = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('Translation fetch failed: ' + res.status);
            const translations = await res.json();
            window.NOTIF_TRANSLATIONS = translations;
            const container = document.getElementById('notificationPageContainer');
            if (!container) return;
            container.querySelectorAll('[data-i18n]').forEach(el => {
                const key = el.getAttribute('data-i18n');
                const txt = key.split('.').reduce((o,k) => (o && o[k] !== undefined) ? o[k] : null, translations);
                if (txt !== null && txt !== undefined) el.textContent = txt;
            });
            container.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
                const key = el.getAttribute('data-i18n-placeholder');
                const txt = key.split('.').reduce((o,k) => (o && o[k] !== undefined) ? o[k] : null, translations);
                if (txt !== null && txt !== undefined) el.placeholder = txt;
            });
        } catch(err) {
            console.warn('[Notifications] Translation load failed:', err);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyTranslations);
    } else {
        setTimeout(applyTranslations, 50);
    }
})();
</script>

<!-- Load scripts -->
<?php if ($isFragment): ?>
<script src="/admin/assets/js/admin_framework.js?v=<?= assetVer() ?>"></script>
<script src="/admin/assets/js/pages/notification.js?v=<?= assetVer() ?>"></script>
<script>
(function(){
    let attempts = 0, maxAttempts = 50;
    const interval = setInterval(function(){
        attempts++;
        if (window.AdminFramework && window.Notifications && typeof window.Notifications.init === 'function') {
            clearInterval(interval);
            try {
                const p = window.Notifications.init();
                if (p && typeof p.then === 'function') {
                    p.then(()=>console.log('[Notifications] init done')).catch(e=>console.error('[Notifications] init error',e));
                }
            } catch(e) {
                console.error('[Notifications] init threw', e);
            }
        } else if (attempts > maxAttempts) {
            clearInterval(interval);
            console.error('[Notifications] Timeout: Framework:', !!window.AdminFramework, 'Module:', !!window.Notifications);
        }
    }, 100);
})();
</script>
<?php else: ?>
<script src="/admin/assets/js/pages/notification.js?v=<?= assetVer() ?>"></script>
<?php endif; ?>

<?php
if (!$isFragment) {
    require_once __DIR__ . '/../includes/footer.php';
}
?>