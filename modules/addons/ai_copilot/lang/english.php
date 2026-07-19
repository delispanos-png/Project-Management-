<?php
/**
 * AI Support Copilot
 * English language file
 *
 * @package    WHMCS
 * @author     WHMCS Limited <development@whmcs.com>
 * @copyright  Copyright (c) WHMCS Limited 2005-2025
 * @license    https://www.whmcs.com/license/ WHMCS Eula
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

// Module metadata
$_ADDONLANG['name'] = 'AI Support Copilot';
$_ADDONLANG['description'] = 'Provides AI Support Copilot functionality';
$_ADDONLANG['tagline'] = 'AI-powered support assistant for WHMCS';

// Configuration fields
$_ADDONLANG['license_id'] = 'License ID';
$_ADDONLANG['license_key'] = 'License Key';
$_ADDONLANG['license_id_description'] = 'Enter a valid license ID to enable requests.';
$_ADDONLANG['license_key_description'] = 'Enter a valid license key to enable requests.';
$_ADDONLANG['custom_instructions'] = 'Custom Instructions';
$_ADDONLANG['custom_instructions_description'] = 'Enter additional prompt instructions to customize and improve AI-generated responses. You can specify company policies, guidelines, or any other instructions to follow when processing support tickets.';

// Lifecycle messages
$_ADDONLANG['activation_success'] = 'AI Support Copilot addon activated';
$_ADDONLANG['deactivation_success'] = 'AI Support Copilot addon deactivated';
$_ADDONLANG['deactivation_failed'] = 'Deactivation failed: %s';

// License validation messages
$_ADDONLANG['license_validated'] = 'License validated successfully';
$_ADDONLANG['license_validation_failed'] = 'License validation failed';
$_ADDONLANG['license_validation_error'] = 'License validation error';
$_ADDONLANG['license_active'] = 'License is active';
$_ADDONLANG['license_credentials_not_configured'] = 'License credentials not configured';

// Access control
$_ADDONLANG['access_denied'] = 'Access denied';
$_ADDONLANG['unauthorized_license_check'] = 'Unauthorized license check attempt';

// Error messages (addon-specific)
$_ADDONLANG['error_occurred'] = 'An error occurred while checking license';
$_ADDONLANG['unexpected_error'] = 'Unexpected error occurred';

// Display messages
$_ADDONLANG['key_features'] = 'Key Features';
$_ADDONLANG['developed_by'] = 'Developed by';

// Validation errors (addon config validation)
$_ADDONLANG['license_key_required'] = 'License Key is required when License ID is provided';
$_ADDONLANG['license_id_required'] = 'License ID is required when License Key is provided';
$_ADDONLANG['license_id_without_key'] = 'License ID provided without License Key';
$_ADDONLANG['license_key_without_id'] = 'License Key provided without License ID';

// ============================================================================
// Common translations (also exist in server module for independence)
// Note: These are duplicated in modules/servers/aisc/lang/english.php
// ============================================================================

// Error codes (from API)
$_ADDONLANG['invalid_signature'] = 'Invalid license key';
$_ADDONLANG['license_duplicated'] = 'License is already in use on another installation';
$_ADDONLANG['suspended'] = 'License is suspended';
$_ADDONLANG['not_found'] = 'License not found';
$_ADDONLANG['invalid_request'] = 'Invalid request parameters';
$_ADDONLANG['unauthorized'] = 'Unauthorized access';
$_ADDONLANG['error_unknown'] = 'Unknown error occurred';

// API Connection errors
$_ADDONLANG['failed_to_connect'] = 'Failed to connect to license server';
$_ADDONLANG['failed_to_validate'] = 'Failed to validate license';
$_ADDONLANG['curl_not_available'] = 'cURL extension is not available';
$_ADDONLANG['empty_response'] = 'Received empty response from license server';
$_ADDONLANG['invalid_response'] = 'Invalid JSON response from license server';
$_ADDONLANG['server_error'] = 'License server error';

// General messages
$_ADDONLANG['access_denied'] = 'Access denied';

// ============================================================================
// AI Consumption Dashboard Translations
// ============================================================================

// Warning messages
$_ADDONLANG['warning_high_usage'] = 'High Usage Warning';
$_ADDONLANG['warning_critical_usage'] = 'Critical Usage Level';
$_ADDONLANG['warning_usage_message'] = 'You have used <strong>%s%%</strong> of your AI token allocation.';
$_ADDONLANG['warning_consider_upgrade'] = 'Please consider upgrading your plan or monitoring usage closely.';
$_ADDONLANG['warning_monitor_usage'] = 'Consider monitoring your usage to avoid reaching the limit.';

// Metric labels
$_ADDONLANG['metric_consumed_tokens'] = 'Tickets Processed';
$_ADDONLANG['metric_remaining_tokens'] = 'Remaining Tickets';
$_ADDONLANG['metric_plan_limit'] = 'Plan Limit';
$_ADDONLANG['metric_daily_average'] = 'Daily Average';
$_ADDONLANG['metric_token_usage'] = 'Ticket Usage';

// Billing period
$_ADDONLANG['billing_current_period_start'] = 'Current Period Start';
$_ADDONLANG['billing_next_renewal'] = 'Next Renewal';

// Progress bar
$_ADDONLANG['progress_tokens_used'] = '%s of %s tickets processed.';

// Recent tickets
$_ADDONLANG['recent_tickets_title'] = 'Recent AI-Resolved Tickets';
$_ADDONLANG['recent_tickets_count'] = '<strong>%s</strong> tickets recently processed by AI';
$_ADDONLANG['no_ai_tickets_found'] = 'No AI-resolved tickets found.';

// Error messages
$_ADDONLANG['unable_to_load_data'] = 'Unable to Load Consumption Data';
$_ADDONLANG['check_license_config'] = 'Please check your license configuration or contact support if the problem persists.';
$_ADDONLANG['error_no_tickets_selected'] = 'No tickets selected for export';
$_ADDONLANG['error_no_tickets_found'] = 'No tickets found for export';

// Table headers
$_ADDONLANG['table_ticket_number'] = 'Ticket #';
$_ADDONLANG['table_subject'] = 'Subject';
$_ADDONLANG['table_assigned_to'] = 'Assigned To';
$_ADDONLANG['table_client'] = 'Client';
$_ADDONLANG['table_created_at'] = 'Created At';
$_ADDONLANG['table_ai_charged_at'] = 'AI Charged At';
$_ADDONLANG['table_status'] = 'Status';

// Button labels
$_ADDONLANG['btn_view_all_tickets'] = 'View All AI-Resolved Tickets';
$_ADDONLANG['btn_reset'] = 'Reset';
$_ADDONLANG['btn_export_selected'] = 'Export Selected';

// Messages
$_ADDONLANG['no_records_found'] = 'No records found.';
$_ADDONLANG['unassigned'] = 'Unassigned';

// Placeholders
$_ADDONLANG['placeholder_search'] = 'Ticket ID or Subject';
$_ADDONLANG['ai_date_range'] = 'AI Processing Date Range';

// Quick access
$_ADDONLANG['quick_access'] = 'Quick Access';
$_ADDONLANG['ai_consumption_center'] = 'AI Consumption Center';
$_ADDONLANG['ai_resolved_tickets'] = 'AI-Resolved Tickets';
$_ADDONLANG['quick_access_description'] = 'View AI ticket statistics and billing information, and track tickets that AI processed.';

// ============================================================================
// AI Service Error Messages - Addon Specific
// Note: Common errors (llm_timeout, rate_limit_exceeded, etc.) are in admin/lang/english.php
// These are addon-specific errors for consumption dashboard and licensing
// ============================================================================

// License & Balance Errors (Addon specific)
$_ADDONLANG['aisc_errors']['insufficient_balance'] = 'Insufficient credit balance. Add credits to your account and try again.';
$_ADDONLANG['aisc_errors']['license_invalid'] = 'The provided license is invalid.';
$_ADDONLANG['aisc_errors']['license_duplicated'] = 'This license is already registered in the system.';

// OAuth Errors (Addon specific)
$_ADDONLANG['aisc_errors']['oauth_invalid_token'] = 'OAuth token is invalid or has been revoked.';
$_ADDONLANG['aisc_errors']['oauth_token_expired'] = 'OAuth token has expired. Please authenticate again.';
$_ADDONLANG['aisc_errors']['oauth_invalid_client'] = 'OAuth client credentials are invalid.';

// Detailed Technical Errors (for addon administration/debugging)
$_ADDONLANG['aisc_errors']['invalid_trace_id'] = 'The trace ID must be a valid UUID format.';
$_ADDONLANG['aisc_errors']['invalid_signature'] = 'Request signature verification failed. Check your license key.';
$_ADDONLANG['aisc_errors']['pii_decryption_failed'] = 'Failed to decrypt the ticket data. Check the encryption keys.';
$_ADDONLANG['aisc_errors']['pii_invalid_data'] = 'The provided data is invalid or corrupted.';
$_ADDONLANG['aisc_errors']['aisc_digest_extraction_failed'] = 'Failed to extract the ticket digest. Try again.';
$_ADDONLANG['aisc_errors']['aisc_prompt_generation_failed'] = 'Failed to generate an AI prompt. Try again.';
$_ADDONLANG['aisc_errors']['aisc_response_store_failed'] = 'Failed to save the AI response. Try again';
$_ADDONLANG['aisc_errors']['aisc_service_timeout'] = 'The AISC service timed out after :timeout seconds. Please try again.';
$_ADDONLANG['aisc_errors']['aisc_invalid_response'] = 'Received an invalid response from AI service.';
$_ADDONLANG['aisc_errors']['aisc_license_validation_failed'] = 'License validation failed. Check your credentials and try again.';
$_ADDONLANG['aisc_errors']['llm_invalid_api_key'] = 'The AI provider API key is invalid. Check your configuration.';
$_ADDONLANG['aisc_errors']['llm_search_failed'] = 'Failed to search the knowledge base with AI. Please try again.';
$_ADDONLANG['aisc_errors']['llm_no_search_results'] = 'No relevant articles found in the knowledge base for this ticket.';
$_ADDONLANG['aisc_errors']['llm_provider_error'] = 'The AI provider (:provider) returned an error: :message';
$_ADDONLANG['aisc_errors']['invalid_star_rating'] = 'Star rating must be between 1 and 5.';
$_ADDONLANG['aisc_errors']['missing_encrypted_data'] = 'Missing required field: encrypted_data';
$_ADDONLANG['aisc_errors']['database_error'] = 'Database operation failed. Please try again.';
$_ADDONLANG['aisc_errors']['record_not_found'] = 'The requested record was not found.';
$_ADDONLANG['aisc_errors']['ticket_not_found'] = 'Ticket not found in the system.';
$_ADDONLANG['aisc_errors']['ticket_access_denied'] = 'You don\'t have permission to access this ticket.';
$_ADDONLANG['aisc_errors']['kb_article_not_found'] = 'Knowledge base article not found.';

// Short error titles for UI (used in consumption dashboard)
$_ADDONLANG['aisc_error_titles']['llm_timeout'] = 'AI Timeout';
$_ADDONLANG['aisc_error_titles']['rate_limit_exceeded'] = 'Rate Limit Exceeded';
$_ADDONLANG['aisc_error_titles']['license_suspended'] = 'License Suspended';
$_ADDONLANG['aisc_error_titles']['service_unavailable'] = 'Service Unavailable';
$_ADDONLANG['aisc_error_titles']['internal_error'] = 'Internal Error';
$_ADDONLANG['aisc_error_titles']['quota_exceeded'] = 'Quota Exceeded';
