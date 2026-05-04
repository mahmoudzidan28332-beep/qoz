  <!-- Translations Modal -->
  <div class="dc-modal-backdrop" id="translationsModal" style="display:none">
    <div class="dc-modal-panel dc-modal-panel--wide">
      <div class="dc-modal-header">
        <h3><?= _dt('translations.title', 'Discount Translations') ?></h3>
        <button class="dc-modal-close btn-close-modal" data-modal="translationsModal" id="btnCloseTranslations" aria-label="Close">&times;</button>
      </div>
      <div class="dc-modal-body">
        <input type="hidden" id="transDiscountId">
        <div class="form-row">
          <div class="form-group">
            <label><?= _dt('translations.language', 'Language') ?></label>
            <select class="form-control" id="transLang"></select>
          </div>
        </div>
        <div class="form-group">
          <label><?= _dt('translations.name', 'Name') ?></label>
          <input type="text" class="form-control" id="transName">
        </div>
        <div class="form-group">
          <label><?= _dt('translations.description', 'Description') ?></label>
          <textarea class="form-control" id="transDescription" rows="3"></textarea>
        </div>
        <div class="form-group">
          <label><?= _dt('translations.terms_conditions', 'Terms & Conditions') ?></label>
          <textarea class="form-control" id="transTermsConditions" rows="3"></textarea>
        </div>
        <div class="form-group">
          <label><?= _dt('translations.marketing_badge', 'Marketing Badge') ?></label>
          <input type="text" class="form-control" id="transMarketingBadge">
        </div>
        <button class="btn btn-primary btn-sm" id="btnSaveTranslation" data-btn-slug="primary"><?= _dt('translations.save', 'Save Translation') ?></button>
        <table class="data-table dc-sub-table">
          <thead>
            <tr>
              <th><?= _dt('translations.language', 'Language') ?></th>
              <th><?= _dt('translations.name', 'Name') ?></th>
              <th><?= _dt('translations.description', 'Description') ?></th>
              <th><?= _dt('translations.terms_conditions', 'Terms & Conditions') ?></th>
              <th><?= _dt('translations.marketing_badge', 'Marketing Badge') ?></th>
              <th><?= _dt('table.actions', 'Actions') ?></th>
            </tr>
          </thead>
          <tbody id="translationsBody"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Scopes Modal -->
  <div class="dc-modal-backdrop" id="scopesModal" style="display:none">
    <div class="dc-modal-panel dc-modal-panel--wide">
      <div class="dc-modal-header">
        <h3><?= _dt('scopes.title', 'Discount Scopes') ?></h3>
        <button class="dc-modal-close btn-close-modal" data-modal="scopesModal" id="btnCloseScopes" aria-label="Close">&times;</button>
      </div>
      <div class="dc-modal-body">
        <input type="hidden" id="scopesDiscountId">
        <div class="form-row">
          <div class="form-group">
            <label><?= _dt('scopes.scope_type', 'Scope Type') ?></label>
            <select class="form-control" id="scopeType">
              <option value="all"><?= _dt('scopes.all', 'All') ?></option>
              <option value="product"><?= _dt('scopes.product', 'Product') ?></option>
              <option value="category"><?= _dt('scopes.category', 'Category') ?></option>
              <option value="brand"><?= _dt('scopes.brand', 'Brand') ?></option>
              <option value="collection"><?= _dt('scopes.collection', 'Collection') ?></option>
              <option value="supplier"><?= _dt('scopes.supplier', 'Supplier') ?></option>
              <option value="customer_group"><?= _dt('scopes.customer_group', 'Customer Group') ?></option>
              <option value="entity"><?= _dt('scopes.entity', 'Entity') ?></option>
            </select>
          </div>
          <div class="form-group">
            <label id="scopeIdLabel"><?= _dt('scopes.scope_id', 'Scope ID') ?></label>
            <input type="text" class="form-control" id="scopeId" placeholder="<?= _dt('scopes.enter_id', 'Enter ID') ?>">
            <span id="scopeIdName" class="lookup-name"></span>
          </div>
          <div class="form-group form-group-btn">
            <button class="btn btn-primary btn-sm" id="btnAddScope" data-btn-slug="primary">+ <?= _dt('scopes.add', 'Add Scope') ?></button>
          </div>
        </div>
        <table class="data-table dc-sub-table">
          <thead>
            <tr>
              <th><?= _dt('scopes.scope_type', 'Scope Type') ?></th>
              <th><?= _dt('scopes.scope_id', 'Scope ID') ?></th>
              <th><?= _dt('scopes.name', 'Name') ?></th>
              <th><?= _dt('table.actions', 'Actions') ?></th>
            </tr>
          </thead>
          <tbody id="scopesBody"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Conditions Modal -->
  <div class="dc-modal-backdrop" id="conditionsModal" style="display:none">
    <div class="dc-modal-panel dc-modal-panel--wide">
      <div class="dc-modal-header">
        <h3><?= _dt('conditions.title', 'Discount Conditions') ?></h3>
        <button class="dc-modal-close btn-close-modal" data-modal="conditionsModal" id="btnCloseConditions" aria-label="Close">&times;</button>
      </div>
      <div class="dc-modal-body">
        <input type="hidden" id="conditionsDiscountId">
        <div class="form-row">
          <div class="form-group">
            <label><?= _dt('conditions.condition_type', 'Condition Type') ?></label>
            <select class="form-control" id="conditionType">
              <option value="min_cart_total"><?= _dt('conditions.min_cart_total', 'Min Cart Total') ?></option>
              <option value="min_items_count"><?= _dt('conditions.min_items_count', 'Min Items Count') ?></option>
              <option value="first_order_only"><?= _dt('conditions.first_order_only', 'First Order Only') ?></option>
              <option value="weekend_only"><?= _dt('conditions.weekend_only', 'Weekend Only') ?></option>
              <option value="specific_payment_method"><?= _dt('conditions.specific_payment_method', 'Specific Payment Method') ?></option>
              <option value="customer_segment"><?= _dt('conditions.customer_segment', 'Customer Segment') ?></option>
              <option value="geo_location"><?= _dt('conditions.geo_location', 'Geo Location') ?></option>
              <option value="time_window"><?= _dt('conditions.time_window', 'Time Window') ?></option>
              <option value="custom_rule"><?= _dt('conditions.custom_rule', 'Custom Rule') ?></option>
            </select>
          </div>
          <div class="form-group">
            <label><?= _dt('conditions.operator', 'Operator') ?></label>
            <select class="form-control" id="conditionOperator">
              <option value="=">=</option>
              <option value="!=">!=</option>
              <option value=">">&gt;</option>
              <option value=">=">&gt;=</option>
              <option value="<">&lt;</option>
              <option value="<=">&lt;=</option>
              <option value="in">IN</option>
              <option value="not_in">NOT IN</option>
              <option value="between">BETWEEN</option>
              <option value="contains"><?= _dt('conditions.contains', 'Contains') ?></option>
            </select>
          </div>
          <div class="form-group">
            <label><?= _dt('conditions.value', 'Value') ?></label>
            <input type="text" class="form-control" id="conditionValue">
          </div>
          <div class="form-group form-group-btn">
            <button class="btn btn-primary btn-sm" id="btnAddCondition" data-btn-slug="primary">+ <?= _dt('conditions.add', 'Add Condition') ?></button>
          </div>
        </div>
        <table class="data-table dc-sub-table">
          <thead>
            <tr>
              <th><?= _dt('conditions.condition_type', 'Condition Type') ?></th>
              <th><?= _dt('conditions.operator', 'Operator') ?></th>
              <th><?= _dt('conditions.value', 'Value') ?></th>
              <th><?= _dt('table.actions', 'Actions') ?></th>
            </tr>
          </thead>
          <tbody id="conditionsBody"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Actions Modal -->
  <div class="dc-modal-backdrop" id="actionsModal" style="display:none">
    <div class="dc-modal-panel dc-modal-panel--wide">
      <div class="dc-modal-header">
        <h3><?= _dt('actions.title', 'Discount Actions') ?></h3>
        <button class="dc-modal-close btn-close-modal" data-modal="actionsModal" id="btnCloseActions" aria-label="Close">&times;</button>
      </div>
      <div class="dc-modal-body">
        <input type="hidden" id="actionsDiscountId">
        <div class="form-row">
          <div class="form-group">
            <label><?= _dt('actions.action_type', 'Action Type') ?></label>
            <select class="form-control" id="actionType">
              <option value="percentage"><?= _dt('actions.percentage', 'Percentage') ?></option>
              <option value="fixed"><?= _dt('actions.fixed', 'Fixed Amount') ?></option>
              <option value="free_shipping"><?= _dt('actions.free_shipping', 'Free Shipping') ?></option>
              <option value="buy_x_get_y"><?= _dt('actions.buy_x_get_y', 'Buy X Get Y') ?></option>
            </select>
          </div>
          <div class="form-group">
            <label><?= _dt('actions.action_value', 'Action Value') ?></label>
            <input type="text" class="form-control" id="actionValue">
          </div>
          <div class="form-group form-group-btn">
            <button class="btn btn-primary btn-sm" id="btnAddAction" data-btn-slug="primary">+ <?= _dt('actions.add', 'Add Action') ?></button>
          </div>
        </div>
        <table class="data-table dc-sub-table">
          <thead>
            <tr>
              <th><?= _dt('actions.action_type', 'Action Type') ?></th>
              <th><?= _dt('actions.action_value', 'Action Value') ?></th>
              <th><?= _dt('table.actions', 'Actions') ?></th>
            </tr>
          </thead>
          <tbody id="actionsBody"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Exclusions Modal -->
  <div class="dc-modal-backdrop" id="exclusionsModal" style="display:none">
    <div class="dc-modal-panel dc-modal-panel--wide">
      <div class="dc-modal-header">
        <h3><?= _dt('exclusions.title', 'Discount Exclusions') ?></h3>
        <button class="dc-modal-close btn-close-modal" data-modal="exclusionsModal" id="btnCloseExclusions" aria-label="Close">&times;</button>
      </div>
      <div class="dc-modal-body">
        <input type="hidden" id="exclusionsDiscountId">
        <div class="form-row">
          <div class="form-group">
            <label><?= _dt('exclusions.select_discount', 'Select Discount to Exclude') ?></label>
            <select class="form-control" id="excludeDiscountSelect">
              <option value=""><?= _dt('exclusions.select', 'Select...') ?></option>
            </select>
          </div>
          <div class="form-group form-group-btn">
            <button class="btn btn-primary btn-sm" id="btnAddExclusion" data-btn-slug="primary">+ <?= _dt('exclusions.add', 'Add Exclusion') ?></button>
          </div>
        </div>
        <table class="data-table dc-sub-table">
          <thead>
            <tr>
              <th><?= _dt('exclusions.excluded_discount', 'Excluded Discount') ?></th>
              <th><?= _dt('table.actions', 'Actions') ?></th>
            </tr>
          </thead>
          <tbody id="exclusionsBody"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Redemptions Modal -->
  <div class="dc-modal-backdrop" id="redemptionsModal" style="display:none">
    <div class="dc-modal-panel dc-modal-panel--wide">
      <div class="dc-modal-header">
        <h3><?= _dt('redemptions.title', 'Discount Redemptions') ?></h3>
        <button class="dc-modal-close btn-close-modal" data-modal="redemptionsModal" id="btnCloseRedemptions" aria-label="Close">&times;</button>
      </div>
      <div class="dc-modal-body">
        <input type="hidden" id="redemptionsDiscountId">
        <table class="data-table">
          <thead>
            <tr>
              <th><?= _dt('redemptions.user_id', 'User ID') ?></th>
              <th><?= _dt('redemptions.order_id', 'Order ID') ?></th>
              <th><?= _dt('redemptions.amount_discounted', 'Amount Discounted') ?></th>
              <th><?= _dt('redemptions.currency_code', 'Currency') ?></th>
              <th><?= _dt('redemptions.redeemed_at', 'Redeemed At') ?></th>
            </tr>
          </thead>
          <tbody id="redemptionsBody"></tbody>
        </table>
        <div class="pagination-wrapper">
          <div class="pagination-info" id="redemptionsPaginationInfo"></div>
          <div class="pagination" id="redemptionsPagination"></div>
        </div>
      </div>
    </div>
  </div>
