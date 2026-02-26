CREATE TABLE IF NOT EXISTS tenants (
    id VARCHAR(64) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role_key VARCHAR(64) NOT NULL,
    is_superadmin TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_tenant (tenant_id)
);

CREATE TABLE IF NOT EXISTS roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    role_key VARCHAR(64) NOT NULL,
    name VARCHAR(255) NOT NULL,
    UNIQUE KEY uq_role (tenant_id, role_key)
);

CREATE TABLE IF NOT EXISTS role_permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    role_key VARCHAR(64) NOT NULL,
    permission_key VARCHAR(128) NOT NULL,
    UNIQUE KEY uq_role_perm (tenant_id, role_key, permission_key)
);

CREATE TABLE IF NOT EXISTS plugin_routes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    plugin VARCHAR(128) NOT NULL,
    route VARCHAR(255) NOT NULL,
    permission_key VARCHAR(128) NOT NULL
);

CREATE TABLE IF NOT EXISTS plugin_definitions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plugin_key VARCHAR(128) NOT NULL,
    version VARCHAR(32) NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    capabilities_json JSON NOT NULL,
    required_permissions_json JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_plugin_definition_key (plugin_key)
);

CREATE TABLE IF NOT EXISTS plugin_hooks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    plugin VARCHAR(128) NOT NULL,
    hook_name VARCHAR(128) NOT NULL,
    config_json JSON NULL
);

CREATE TABLE IF NOT EXISTS tenant_plugins (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    plugin_key VARCHAR(128) NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    version VARCHAR(32) NOT NULL DEFAULT '1.0.0',
    lifecycle_status VARCHAR(16) NOT NULL DEFAULT 'installed',
    capabilities_json JSON NULL,
    required_permissions_json JSON NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenant_plugin (tenant_id, plugin_key)
);

CREATE TABLE IF NOT EXISTS tenant_feature_flags (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    company_id VARCHAR(64) NOT NULL DEFAULT 'default',
    flag_key VARCHAR(128) NOT NULL,
    flag_value TINYINT(1) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenant_company_flag (tenant_id, company_id, flag_key)
);

CREATE TABLE IF NOT EXISTS domain_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    event_name VARCHAR(128) NOT NULL,
    aggregate_type VARCHAR(64) NOT NULL,
    aggregate_id VARCHAR(128) NOT NULL,
    payload_json JSON NOT NULL,
    event_status VARCHAR(32) NOT NULL DEFAULT 'pending',
    available_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    INDEX idx_domain_event_tenant_status (tenant_id, event_status, available_at),
    INDEX idx_domain_event_name (event_name, created_at)
);

CREATE TABLE IF NOT EXISTS outbox_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    domain_event_id BIGINT UNSIGNED NOT NULL,
    destination VARCHAR(64) NOT NULL,
    message_key VARCHAR(191) NOT NULL,
    payload_json JSON NOT NULL,
    delivery_status VARCHAR(32) NOT NULL DEFAULT 'pending',
    retry_count INT NOT NULL DEFAULT 0,
    last_error VARCHAR(512) NULL,
    next_retry_at TIMESTAMP NULL,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_outbox_message_key (message_key),
    INDEX idx_outbox_delivery (delivery_status, next_retry_at, created_at),
    CONSTRAINT fk_outbox_domain_event FOREIGN KEY (domain_event_id) REFERENCES domain_events (id)
);

CREATE TABLE IF NOT EXISTS email_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    template_key VARCHAR(128) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body_html MEDIUMTEXT NOT NULL,
    UNIQUE KEY uq_email_tpl (tenant_id, template_key)
);

CREATE TABLE IF NOT EXISTS pdf_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    template_key VARCHAR(128) NOT NULL,
    body_html MEDIUMTEXT NOT NULL,
    UNIQUE KEY uq_pdf_tpl (tenant_id, template_key)
);

CREATE TABLE IF NOT EXISTS email_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    recipient VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    template_key VARCHAR(128) NOT NULL,
    context_json JSON NULL,
    status VARCHAR(32) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS crm_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS refresh_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token_id VARCHAR(64) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    tenant_id VARCHAR(64) NOT NULL,
    user_id VARCHAR(255) NOT NULL,
    entrypoint VARCHAR(64) NOT NULL,
    permissions_json JSON NOT NULL,
    is_superadmin TINYINT(1) DEFAULT 0,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(512) NULL,
    expires_at TIMESTAMP NOT NULL,
    revoked TINYINT(1) DEFAULT 0,
    revoked_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_refresh_token_id (token_id),
    UNIQUE KEY uq_refresh_token_hash (token_hash),
    INDEX idx_refresh_user (tenant_id, user_id),
    INDEX idx_refresh_expires (expires_at)
);

CREATE TABLE IF NOT EXISTS tenant_smtp_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    host VARCHAR(255) NOT NULL,
    port INT NOT NULL DEFAULT 587,
    username VARCHAR(255) NULL,
    password VARCHAR(255) NULL,
    encryption VARCHAR(16) NOT NULL DEFAULT 'tls',
    from_email VARCHAR(255) NOT NULL,
    from_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenant_smtp (tenant_id)
);

CREATE TABLE IF NOT EXISTS stripe_webhook_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stripe_event_id VARCHAR(128) NOT NULL,
    event_type VARCHAR(128) NOT NULL,
    tenant_id VARCHAR(64) NULL,
    stripe_customer_id VARCHAR(128) NULL,
    stripe_subscription_id VARCHAR(128) NULL,
    event_status VARCHAR(32) NOT NULL DEFAULT 'received',
    error_message VARCHAR(512) NULL,
    payload_json JSON NOT NULL,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_stripe_event_id (stripe_event_id),
    INDEX idx_stripe_event_tenant (tenant_id, event_type)
);

CREATE TABLE IF NOT EXISTS tenant_provisioning_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    stripe_event_pk BIGINT UNSIGNED NOT NULL,
    stripe_session_id VARCHAR(128) NOT NULL,
    stripe_customer_id VARCHAR(128) NULL,
    provisioning_status VARCHAR(32) NOT NULL,
    payload_json JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_provisioning_session (stripe_session_id),
    INDEX idx_provisioning_tenant (tenant_id),
    CONSTRAINT fk_provisioning_event FOREIGN KEY (stripe_event_pk) REFERENCES stripe_webhook_events (id)
);

CREATE TABLE IF NOT EXISTS tenant_subscription_entitlements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    stripe_event_pk BIGINT UNSIGNED NOT NULL,
    stripe_subscription_id VARCHAR(128) NOT NULL,
    entitlement_status VARCHAR(32) NOT NULL,
    current_period_end TIMESTAMP NULL,
    payload_json JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenant_subscription (tenant_id, stripe_subscription_id),
    INDEX idx_entitlement_status (tenant_id, entitlement_status),
    CONSTRAINT fk_entitlement_event FOREIGN KEY (stripe_event_pk) REFERENCES stripe_webhook_events (id)
);

CREATE TABLE IF NOT EXISTS stripe_dunning_cases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    stripe_event_pk BIGINT UNSIGNED NOT NULL,
    stripe_invoice_id VARCHAR(128) NOT NULL,
    dunning_status VARCHAR(32) NOT NULL,
    attempt_count INT NOT NULL DEFAULT 0,
    next_payment_attempt_at TIMESTAMP NULL,
    resolved_at TIMESTAMP NULL,
    payload_json JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_dunning_invoice (tenant_id, stripe_invoice_id),
    INDEX idx_dunning_status (tenant_id, dunning_status),
    CONSTRAINT fk_dunning_event FOREIGN KEY (stripe_event_pk) REFERENCES stripe_webhook_events (id)
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    actor_id VARCHAR(128) NOT NULL,
    action_key VARCHAR(128) NOT NULL,
    target_type VARCHAR(64) NOT NULL,
    target_id VARCHAR(128) NOT NULL,
    status VARCHAR(32) NOT NULL,
    metadata_json JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(512) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_tenant_created (tenant_id, created_at),
    INDEX idx_audit_actor (tenant_id, actor_id)
);

CREATE TABLE IF NOT EXISTS approval_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    request_type VARCHAR(64) NOT NULL,
    target_type VARCHAR(64) NOT NULL,
    target_id VARCHAR(128) NOT NULL,
    change_payload_json JSON NOT NULL,
    requested_by VARCHAR(128) NOT NULL,
    approved_by VARCHAR(128) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    decided_at TIMESTAMP NULL,
    INDEX idx_approval_tenant_status (tenant_id, status, created_at),
    INDEX idx_approval_target (tenant_id, target_type, target_id)
);

CREATE TABLE IF NOT EXISTS tenant_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    account_type VARCHAR(32) NOT NULL,
    role_id BIGINT UNSIGNED NULL,
    first_name VARCHAR(120) NOT NULL,
    last_name VARCHAR(120) NOT NULL,
    company VARCHAR(255) NULL,
    street VARCHAR(255) NULL,
    house_number VARCHAR(50) NULL,
    postal_code VARCHAR(32) NULL,
    city VARCHAR(120) NULL,
    country VARCHAR(120) NULL,
    phone VARCHAR(64) NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    vat_number VARCHAR(64) NULL,
    email_confirmed TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    UNIQUE KEY uq_tenant_accounts_email (tenant_id, email),
    INDEX idx_tenant_accounts_type (tenant_id, account_type),
    INDEX idx_tenant_accounts_role (tenant_id, role_id),
    CONSTRAINT fk_tenant_accounts_role FOREIGN KEY (role_id) REFERENCES roles (id)
);

CREATE TABLE IF NOT EXISTS billing_customers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    customer_type VARCHAR(32) NOT NULL,
    company_name VARCHAR(255) NULL,
    first_name VARCHAR(120) NULL,
    last_name VARCHAR(120) NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(64) NULL,
    vat_id VARCHAR(64) NULL,
    currency_code CHAR(3) NOT NULL DEFAULT 'EUR',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_billing_customers_tenant (tenant_id, created_at)
);

CREATE TABLE IF NOT EXISTS billing_customer_addresses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    address_type VARCHAR(32) NOT NULL,
    company_name VARCHAR(255) NULL,
    first_name VARCHAR(120) NULL,
    last_name VARCHAR(120) NULL,
    street VARCHAR(255) NULL,
    house_number VARCHAR(50) NULL,
    postal_code VARCHAR(32) NULL,
    city VARCHAR(120) NULL,
    country VARCHAR(120) NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(64) NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_billing_customer_addresses (tenant_id, customer_id, address_type),
    CONSTRAINT fk_billing_customer_address_customer FOREIGN KEY (customer_id) REFERENCES billing_customers (id)
);

CREATE TABLE IF NOT EXISTS billing_customer_contacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    first_name VARCHAR(120) NULL,
    last_name VARCHAR(120) NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(64) NULL,
    role_label VARCHAR(120) NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_billing_customer_contacts (tenant_id, customer_id),
    CONSTRAINT fk_billing_customer_contact_customer FOREIGN KEY (customer_id) REFERENCES billing_customers (id)
);

CREATE TABLE IF NOT EXISTS billing_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    plugin_key VARCHAR(128) NOT NULL DEFAULT 'billing_core',
    document_type VARCHAR(32) NOT NULL,
    document_number VARCHAR(64) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    customer_id BIGINT UNSIGNED NULL,
    customer_name_snapshot VARCHAR(255) NULL,
    reference_document_id BIGINT UNSIGNED NULL,
    currency_code CHAR(3) NOT NULL DEFAULT 'EUR',
    exchange_rate DECIMAL(18,6) NOT NULL DEFAULT 1.000000,
    subtotal_net DECIMAL(18,2) NOT NULL DEFAULT 0,
    discount_total DECIMAL(18,2) NOT NULL DEFAULT 0,
    shipping_total DECIMAL(18,2) NOT NULL DEFAULT 0,
    fees_total DECIMAL(18,2) NOT NULL DEFAULT 0,
    tax_total DECIMAL(18,2) NOT NULL DEFAULT 0,
    grand_total DECIMAL(18,2) NOT NULL DEFAULT 0,
    totals_json JSON NOT NULL,
    due_date DATE NULL,
    finalized_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_billing_documents_tenant_status (tenant_id, status, created_at),
    INDEX idx_billing_documents_number (tenant_id, document_number),
    CONSTRAINT fk_billing_document_customer FOREIGN KEY (customer_id) REFERENCES billing_customers (id),
    CONSTRAINT fk_billing_document_reference FOREIGN KEY (reference_document_id) REFERENCES billing_documents (id)
);

CREATE TABLE IF NOT EXISTS billing_document_addresses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    document_id BIGINT UNSIGNED NOT NULL,
    address_type VARCHAR(32) NOT NULL,
    company_name VARCHAR(255) NULL,
    first_name VARCHAR(120) NULL,
    last_name VARCHAR(120) NULL,
    street VARCHAR(255) NULL,
    house_number VARCHAR(50) NULL,
    postal_code VARCHAR(32) NULL,
    city VARCHAR(120) NULL,
    country VARCHAR(120) NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(64) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_billing_document_addresses (tenant_id, document_id, address_type),
    CONSTRAINT fk_billing_document_address_document FOREIGN KEY (document_id) REFERENCES billing_documents (id)
);

CREATE TABLE IF NOT EXISTS billing_line_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    document_id BIGINT UNSIGNED NOT NULL,
    position INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    quantity DECIMAL(18,4) NOT NULL,
    unit_price DECIMAL(18,2) NOT NULL,
    discount_percent DECIMAL(8,4) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    tax_rate DECIMAL(8,4) NOT NULL DEFAULT 0,
    line_net DECIMAL(18,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_billing_line_items_document (tenant_id, document_id, position),
    CONSTRAINT fk_billing_line_item_document FOREIGN KEY (document_id) REFERENCES billing_documents (id)
);

CREATE TABLE IF NOT EXISTS billing_tax_breakdowns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    document_id BIGINT UNSIGNED NOT NULL,
    tax_rate DECIMAL(8,4) NOT NULL,
    net_amount DECIMAL(18,2) NOT NULL,
    tax_amount DECIMAL(18,2) NOT NULL,
    gross_amount DECIMAL(18,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_billing_tax_breakdown (tenant_id, document_id, tax_rate),
    CONSTRAINT fk_billing_tax_document FOREIGN KEY (document_id) REFERENCES billing_documents (id)
);

CREATE TABLE IF NOT EXISTS billing_number_counters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    series_key VARCHAR(32) NOT NULL,
    year INT NOT NULL,
    current_number BIGINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_billing_number_counter (tenant_id, series_key, year)
);

CREATE TABLE IF NOT EXISTS billing_document_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    document_id BIGINT UNSIGNED NOT NULL,
    action_key VARCHAR(128) NOT NULL,
    actor_id VARCHAR(128) NOT NULL,
    metadata_json JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_billing_history_document (tenant_id, document_id, created_at),
    CONSTRAINT fk_billing_history_document FOREIGN KEY (document_id) REFERENCES billing_documents (id)
);

CREATE TABLE IF NOT EXISTS billing_payment_links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    document_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(32) NOT NULL,
    payment_link_id VARCHAR(191) NOT NULL,
    payment_url VARCHAR(512) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'open',
    amount DECIMAL(18,2) NOT NULL,
    currency_code CHAR(3) NOT NULL DEFAULT 'EUR',
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_billing_payment_link (tenant_id, provider, payment_link_id),
    INDEX idx_billing_payment_links_document (tenant_id, document_id, status),
    CONSTRAINT fk_billing_payment_link_document FOREIGN KEY (document_id) REFERENCES billing_documents (id)
);

CREATE TABLE IF NOT EXISTS billing_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    document_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(32) NOT NULL DEFAULT 'manual',
    external_payment_id VARCHAR(191) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'received',
    amount_paid DECIMAL(18,2) NOT NULL,
    fee_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    notes VARCHAR(512) NULL,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_billing_payments_document (tenant_id, document_id, status),
    CONSTRAINT fk_billing_payment_document FOREIGN KEY (document_id) REFERENCES billing_documents (id)
);

CREATE TABLE IF NOT EXISTS billing_dunning_configs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    grace_days INT NOT NULL DEFAULT 3,
    interest_rate_percent DECIMAL(8,2) NOT NULL DEFAULT 5.00,
    fee_level_1 DECIMAL(18,2) NOT NULL DEFAULT 2.50,
    fee_level_2 DECIMAL(18,2) NOT NULL DEFAULT 5.00,
    fee_level_3 DECIMAL(18,2) NOT NULL DEFAULT 7.50,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_billing_dunning_config_tenant (tenant_id)
);

CREATE TABLE IF NOT EXISTS billing_dunning_cases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    document_id BIGINT UNSIGNED NOT NULL,
    current_level INT NOT NULL DEFAULT 1,
    outstanding_amount DECIMAL(18,2) NOT NULL,
    fee_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    interest_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    last_notice_at TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_billing_dunning_case (tenant_id, document_id),
    INDEX idx_billing_dunning_level (tenant_id, current_level, updated_at),
    CONSTRAINT fk_billing_dunning_document FOREIGN KEY (document_id) REFERENCES billing_documents (id)
);

CREATE TABLE IF NOT EXISTS billing_dunning_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    document_id BIGINT UNSIGNED NOT NULL,
    dunning_level INT NOT NULL,
    outstanding_amount DECIMAL(18,2) NOT NULL,
    fee_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    interest_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_billing_dunning_events (tenant_id, document_id, dunning_level, sent_at),
    CONSTRAINT fk_billing_dunning_event_document FOREIGN KEY (document_id) REFERENCES billing_documents (id)
);

CREATE TABLE IF NOT EXISTS tenant_bank_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    account_holder VARCHAR(255) NULL,
    iban VARCHAR(64) NOT NULL,
    bic VARCHAR(32) NULL,
    bank_name VARCHAR(255) NULL,
    qr_iban_enabled TINYINT(1) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenant_bank_account (tenant_id)
);

CREATE TABLE IF NOT EXISTS tenant_tax_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    business_name VARCHAR(255) NULL,
    tax_number VARCHAR(64) NULL,
    vat_id VARCHAR(64) NULL,
    small_business_enabled TINYINT(1) NOT NULL DEFAULT 0,
    default_tax_category VARCHAR(32) NOT NULL DEFAULT 'standard',
    supply_date_required TINYINT(1) NOT NULL DEFAULT 1,
    service_date_required TINYINT(1) NOT NULL DEFAULT 0,
    country_code CHAR(2) NOT NULL DEFAULT 'DE',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenant_tax_profile (tenant_id)
);

CREATE TABLE IF NOT EXISTS billing_document_compliance (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    document_id BIGINT UNSIGNED NOT NULL,
    plugin_key VARCHAR(128) NOT NULL DEFAULT 'tax_compliance_de',
    is_sealed TINYINT(1) NOT NULL DEFAULT 0,
    seal_hash CHAR(64) NULL,
    sealed_at TIMESTAMP NULL,
    preflight_status VARCHAR(32) NOT NULL DEFAULT 'pending',
    preflight_report_json JSON NULL,
    correction_of_document_id BIGINT UNSIGNED NULL,
    correction_reason VARCHAR(512) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_billing_document_compliance (tenant_id, document_id),
    INDEX idx_billing_document_compliance_status (tenant_id, preflight_status, is_sealed),
    CONSTRAINT fk_billing_document_compliance_document FOREIGN KEY (document_id) REFERENCES billing_documents (id),
    CONSTRAINT fk_billing_document_compliance_correction FOREIGN KEY (correction_of_document_id) REFERENCES billing_documents (id)
);

CREATE TABLE IF NOT EXISTS billing_einvoice_exchange (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    document_id BIGINT UNSIGNED NULL,
    exchange_direction VARCHAR(16) NOT NULL,
    invoice_format VARCHAR(32) NOT NULL,
    payload_json JSON NOT NULL,
    xml_content MEDIUMTEXT NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'received',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_billing_einvoice_direction (tenant_id, exchange_direction, invoice_format, created_at),
    CONSTRAINT fk_billing_einvoice_document FOREIGN KEY (document_id) REFERENCES billing_documents (id)
);

CREATE TABLE IF NOT EXISTS subscription_plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    plugin_key VARCHAR(128) NOT NULL DEFAULT 'subscriptions_billing',
    plan_key VARCHAR(120) NOT NULL,
    name VARCHAR(255) NOT NULL,
    billing_interval VARCHAR(16) NOT NULL DEFAULT 'monthly',
    amount DECIMAL(18,2) NOT NULL,
    currency_code CHAR(3) NOT NULL DEFAULT 'EUR',
    term_months INT NOT NULL DEFAULT 1,
    auto_renew TINYINT(1) NOT NULL DEFAULT 1,
    notice_days INT NOT NULL DEFAULT 30,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_subscription_plan_key (tenant_id, plan_key),
    INDEX idx_subscription_plans_active (tenant_id, is_active, billing_interval)
);

CREATE TABLE IF NOT EXISTS subscription_contracts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    current_term_start DATE NOT NULL,
    current_term_end DATE NOT NULL,
    cancel_at DATE NULL,
    cancelled_at TIMESTAMP NULL,
    payment_method_ref VARCHAR(191) NULL,
    next_billing_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_subscription_contracts_due (tenant_id, status, next_billing_at),
    INDEX idx_subscription_contracts_customer (tenant_id, customer_id),
    CONSTRAINT fk_subscription_contract_customer FOREIGN KEY (customer_id) REFERENCES billing_customers (id),
    CONSTRAINT fk_subscription_contract_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans (id)
);

CREATE TABLE IF NOT EXISTS subscription_cycles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    contract_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(64) NOT NULL,
    amount_delta DECIMAL(18,2) NOT NULL DEFAULT 0,
    currency_code CHAR(3) NOT NULL DEFAULT 'EUR',
    metadata_json JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_subscription_cycles_contract (tenant_id, contract_id, created_at),
    CONSTRAINT fk_subscription_cycles_contract FOREIGN KEY (contract_id) REFERENCES subscription_contracts (id)
);

CREATE TABLE IF NOT EXISTS subscription_invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    contract_id BIGINT UNSIGNED NOT NULL,
    billing_document_id BIGINT UNSIGNED NOT NULL,
    cycle_started_at DATE NOT NULL,
    cycle_ended_at DATE NOT NULL,
    billed_amount DECIMAL(18,2) NOT NULL,
    currency_code CHAR(3) NOT NULL DEFAULT 'EUR',
    retry_attempts INT NOT NULL DEFAULT 0,
    collection_status VARCHAR(32) NOT NULL DEFAULT 'open',
    delivery_status VARCHAR(32) NOT NULL DEFAULT 'pending',
    delivered_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_subscription_invoice_document (tenant_id, billing_document_id),
    INDEX idx_subscription_invoices_status (tenant_id, collection_status, delivery_status),
    CONSTRAINT fk_subscription_invoice_contract FOREIGN KEY (contract_id) REFERENCES subscription_contracts (id),
    CONSTRAINT fk_subscription_invoice_document FOREIGN KEY (billing_document_id) REFERENCES billing_documents (id)
);

CREATE TABLE IF NOT EXISTS subscription_dunning_cases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    contract_id BIGINT UNSIGNED NOT NULL,
    billing_document_id BIGINT UNSIGNED NOT NULL,
    retry_attempts INT NOT NULL DEFAULT 0,
    status VARCHAR(32) NOT NULL DEFAULT 'retrying',
    payment_method_update_required TINYINT(1) NOT NULL DEFAULT 0,
    last_retry_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_subscription_dunning_document (tenant_id, billing_document_id),
    INDEX idx_subscription_dunning_contract (tenant_id, contract_id, status),
    CONSTRAINT fk_subscription_dunning_contract FOREIGN KEY (contract_id) REFERENCES subscription_contracts (id),
    CONSTRAINT fk_subscription_dunning_document FOREIGN KEY (billing_document_id) REFERENCES billing_documents (id)
);

CREATE TABLE IF NOT EXISTS subscription_payment_method_updates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    contract_id BIGINT UNSIGNED NOT NULL,
    token CHAR(32) NOT NULL,
    update_url VARCHAR(512) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'open',
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_subscription_payment_update_token (token),
    INDEX idx_subscription_payment_update_contract (tenant_id, contract_id, status),
    CONSTRAINT fk_subscription_payment_update_contract FOREIGN KEY (contract_id) REFERENCES subscription_contracts (id)
);

CREATE TABLE IF NOT EXISTS document_delivery_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    template_key VARCHAR(128) NOT NULL,
    channel VARCHAR(32) NOT NULL DEFAULT 'email',
    locale VARCHAR(12) NOT NULL DEFAULT 'de',
    subject VARCHAR(255) NOT NULL,
    body_html MEDIUMTEXT NOT NULL,
    body_text MEDIUMTEXT NULL,
    variables_json JSON NULL,
    attachments_json JSON NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_document_delivery_template (tenant_id, template_key, channel, locale),
    INDEX idx_document_delivery_template_channel (tenant_id, channel, locale)
);

CREATE TABLE IF NOT EXISTS document_delivery_provider_configs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    provider VARCHAR(32) NOT NULL DEFAULT 'smtp',
    from_email VARCHAR(255) NULL,
    from_name VARCHAR(255) NULL,
    reply_to VARCHAR(255) NULL,
    smtp_host VARCHAR(255) NULL,
    smtp_port INT NOT NULL DEFAULT 587,
    smtp_username VARCHAR(255) NULL,
    smtp_password VARCHAR(255) NULL,
    smtp_encryption VARCHAR(16) NOT NULL DEFAULT 'tls',
    sendgrid_api_key VARCHAR(255) NULL,
    mailgun_domain VARCHAR(255) NULL,
    mailgun_api_key VARCHAR(255) NULL,
    webhook_signing_secret VARCHAR(255) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_document_delivery_provider_tenant (tenant_id)
);

CREATE TABLE IF NOT EXISTS document_delivery_tracking_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(64) NOT NULL,
    event_type VARCHAR(32) NOT NULL,
    message_id VARCHAR(191) NULL,
    template_key VARCHAR(128) NULL,
    recipient VARCHAR(255) NULL,
    document_id BIGINT UNSIGNED NULL,
    metadata_json JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_document_delivery_tracking_event (tenant_id, event_type, created_at),
    INDEX idx_document_delivery_tracking_document (tenant_id, document_id),
    CONSTRAINT fk_document_delivery_tracking_document FOREIGN KEY (document_id) REFERENCES billing_documents (id)
);
