<?php
global $wpdb;
$table_name = $wpdb->prefix . 'bua_chat_flows';

// Handle Save
// Handle Save / Delete
if (isset($_POST['nueva_flow_action']) && check_admin_referer('nueva_flow_verify')) {
    if ($_POST['nueva_flow_action'] === 'save') {
        $title = sanitize_text_field($_POST['flow_title']);
        $json = wp_unslash($_POST['flow_json']);
        $keywords = sanitize_text_field($_POST['flow_keywords']);

        $wpdb->insert($table_name, [
            'title' => $title,
            'flow_json' => $json,
            'trigger_keywords' => $keywords,
            'is_active' => 1,
            'created_at' => current_time('mysql')
        ]);
        echo '<div class="notice notice-success"><p>Flow saved successfully.</p></div>';
    } elseif ($_POST['nueva_flow_action'] === 'delete') {
        $id = intval($_POST['flow_id']);
        $wpdb->delete($table_name, ['id' => $id]);
        echo '<div class="notice notice-success"><p>Flow deleted successfully.</p></div>';
    }
}

$flows = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");
?>

<div class="wrap">
    <h1>Chat Flows</h1>

    <div style="display:flex; gap:20px;">
        <div style="flex:2;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h3>Create New Flow (Visual Builder)</h3>
                <button type="button" class="button" id="btn-load-template">📂 Load Template (Dummy Flow)</button>
            </div>

            <form method="post" id="nueva-flow-form">
                <?php wp_nonce_field('nueva_flow_verify'); ?>
                <input type="hidden" name="nueva_flow_action" value="save">
                <table class="form-table">
                    <tr>
                        <th>Flow Title</th>
                        <td><input type="text" name="flow_title" class="widefat" required
                                placeholder="e.g. Refund Policy Flow"></td>
                    </tr>
                    <tr>
                        <th>Trigger Keywords</th>
                        <td><input type="text" name="flow_keywords" class="widefat"
                                placeholder="refund, return, money back"></td>
                    </tr>
                </table>

                <!-- Visual Builder Area -->
                <div id="nueva-flow-builder"
                    style="background:#fff; border:1px solid #ddd; padding:20px; margin-top:20px; border-radius:4px;">
                    <h4>Flow Steps</h4>
                    <p class="description">Define the conversation steps. The first step representing the entry point.
                    </p>

                    <div id="flow-steps-container">
                        <!-- Steps will be injected here -->
                    </div>

                    <button type="button" class="button" id="add-flow-step" style="margin-top:10px;">+ Add Step</button>
                </div>

                <!-- Hidden JSON for Backend -->
                <textarea name="flow_json" id="flow_json_hidden" style="display:none;"></textarea>

                <p class="submit">
                    <input type="submit" class="button button-primary" value="Save Flow">
                </p>
            </form>
        </div>

        <script>
            jQuery(document).ready(function ($) {
                let steps = [];

                function renderSteps() {
                    const $container = $('#flow-steps-container');
                    $container.empty();

                    steps.forEach((step, index) => {
                        let optionsHtml = '';

                        // Render Options (if Message Type)
                        if (step.type !== 'condition') {
                            if (!step.options) step.options = [];
                            step.options.forEach((opt, optIndex) => {
                                let actionInputs = '';
                                if (opt.action === 'link') {
                                    actionInputs = `<input type="text" placeholder="https://..." value="${opt.value || ''}" class="flow-opt-value" data-step="${index}" data-opt="${optIndex}" style="flex:1;">`;
                                } else if (opt.action === 'phone') {
                                    actionInputs = `<input type="text" placeholder="Tel No." value="${opt.value || ''}" class="flow-opt-value" data-step="${index}" data-opt="${optIndex}" style="flex:1;">`;
                                } else {
                                    actionInputs = `<input type="text" placeholder="Next Step ID" value="${opt.next}" class="flow-opt-next" data-step="${index}" data-opt="${optIndex}" style="flex:1;">`;
                                }

                                optionsHtml += `
                                <div class="flow-option-row" style="display:flex; gap:10px; margin-bottom:5px; align-items:center;">
                                    <input type="text" placeholder="Label" value="${opt.label}" data-step="${index}" data-opt="${optIndex}" class="flow-opt-label" style="width:120px;">
                                    <select class="flow-opt-action" data-step="${index}" data-opt="${optIndex}" style="width:100px;">
                                        <option value="step" ${opt.action !== 'link' && opt.action !== 'phone' ? 'selected' : ''}>Go to Step</option>
                                        <option value="link" ${opt.action === 'link' ? 'selected' : ''}>Open Link</option>
                                        <option value="phone" ${opt.action === 'phone' ? 'selected' : ''}>Call</option>
                                    </select>
                                    ${actionInputs}
                                    <button type="button" class="button remove-opt" data-step="${index}" data-opt="${optIndex}">&times;</button>
                                </div>
                            `;
                            });
                        }

                        // Render Step Body
                        let bodyHtml = '';
                        const isCondition = step.type === 'condition';

                        if (isCondition) {
                            let condExtra = '';
                            if (step.condition === 'last_message_contains') {
                                condExtra = `<input type="text" class="widefat step-cond-value" placeholder="keyword (e.g. human)" value="${step.condition_value || ''}" data-index="${index}" style="margin-top:5px;">`;
                            }

                            bodyHtml = `
                                <div style="background:#fff3cd; padding:10px; border-radius:4px;">
                                    <label><strong>Condition Check:</strong></label>
                                    <select class="widefat step-condition" data-index="${index}" style="margin-bottom:10px;">
                                        <option value="is_user_logged_in" ${step.condition === 'is_user_logged_in' ? 'selected' : ''}>Is User Logged In?</option>
                                        <option value="last_message_contains" ${step.condition === 'last_message_contains' ? 'selected' : ''}>Last Message Contains...</option>
                                    </select>
                                    ${condExtra}
                                    <div style="display:flex; gap:20px; margin-top:10px;">
                                        <div style="flex:1;">
                                            <label>If TRUE (Next Step ID):</label>
                                            <input type="text" class="widefat step-true" value="${step.next_true || ''}" data-index="${index}">
                                        </div>
                                        <div style="flex:1;">
                                            <label>If FALSE (Next Step ID):</label>
                                            <input type="text" class="widefat step-false" value="${step.next_false || ''}" data-index="${index}">
                                        </div>
                                    </div>
                                </div>
                            `;
                        } else {
                            bodyHtml = `
                                <label>Bot Message:</label>
                                <textarea class="widefat step-message" data-index="${index}" rows="2">${step.message || ''}</textarea>
                                <div style="margin-top:10px;">
                                    <label>User Buttons:</label>
                                    <div class="step-options">${optionsHtml}</div>
                                    <button type="button" class="button button-small add-option" data-step="${index}">+ Add Button</button>
                                </div>
                            `;
                        }

                        const html = `
                    <div class="flow-step" style="border:1px solid #ccc; padding:15px; margin-bottom:15px; background:${isCondition ? '#fff8e1' : '#f9f9f9'};">
                        <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                            <div style="display:flex; gap:10px; align-items:center;">
                                <strong>ID:</strong> <input type="text" value="${step.id}" class="step-id-input" data-index="${index}" style="width:120px; padding:2px;">
                                <select class="step-type-toggle" data-index="${index}">
                                    <option value="message" ${!isCondition ? 'selected' : ''}>Message</option>
                                    <option value="condition" ${isCondition ? 'selected' : ''}>Logic (If/Else)</option>
                                </select>
                            </div>
                            <button type="button" class="button-link-delete remove-step" data-index="${index}">Remove</button>
                        </div>
                        ${bodyHtml}
                    </div>
                `;
                        $container.append(html);
                    });
                    updateJson();
                }

                function updateJson() {
                    if (steps.length === 0) {
                        $('#flow_json_hidden').val('');
                        return;
                    }
                    const data = {
                        start: steps[0].id,
                        nodes: {}
                    };
                    steps.forEach(step => {
                        const node = { type: step.type || 'message' };
                        if (step.type === 'condition') {
                            node.condition = step.condition;
                            node.condition_value = step.condition_value; // Store extra value
                            node.next_true = step.next_true;
                            node.next_false = step.next_false;
                        } else {
                            node.message = step.message;
                            node.options = step.options;
                        }
                        data.nodes[step.id] = node;
                    });
                    $('#flow_json_hidden').val(JSON.stringify(data));
                }

                // Load Template
                $('#btn-load-template').click(function () {
                    if (!confirm('This will overwrite current steps. Continue?')) return;

                    steps = [
                        { id: 'start_01', type: 'message', message: 'Hello! How can I help you today?', options: [{ label: 'Support', next: 'check_login' }, { label: 'Sales', next: 'sales_info' }] },
                        { id: 'check_login', type: 'condition', condition: 'is_user_logged_in', next_true: 'vip_support', next_false: 'guest_support' },
                        { id: 'vip_support', type: 'message', message: 'Welcome back VIP! Connecting you to an agent...', options: [] },
                        { id: 'guest_support', type: 'message', message: 'Please log in to get premium support.', options: [{ label: 'Login', action: 'link', value: '/wp-login.php' }] },
                        { id: 'sales_info', type: 'message', message: 'Check out our latest offers!', options: [{ label: 'View Offers', action: 'link', value: '/shop' }] }
                    ];
                    renderSteps();
                });

                // Init
                if (steps.length === 0) {
                    $('#add-flow-step').click();
                }

                // --- Event Handlers ---
                $('#add-flow-step').click(function () {
                    steps.push({ id: 'step_' + Math.random().toString(36).substr(2, 5), type: 'message', message: '', options: [] });
                    renderSteps();
                });

                $(document).on('change', '.step-type-toggle', function () {
                    steps[$(this).data('index')].type = $(this).val();
                    renderSteps();
                });

                // Inputs Update
                $(document).on('change keyup', '.step-id-input', function () { steps[$(this).data('index')].id = $(this).val(); updateJson(); });
                $(document).on('change keyup', '.step-message', function () { steps[$(this).data('index')].message = $(this).val(); updateJson(); });

                // Condition
                $(document).on('change', '.step-condition', function () {
                    steps[$(this).data('index')].condition = $(this).val();
                    renderSteps();
                });
                $(document).on('change keyup', '.step-cond-value', function () { steps[$(this).data('index')].condition_value = $(this).val(); updateJson(); });
                $(document).on('change keyup', '.step-true', function () { steps[$(this).data('index')].next_true = $(this).val(); updateJson(); });
                $(document).on('change keyup', '.step-false', function () { steps[$(this).data('index')].next_false = $(this).val(); updateJson(); });

                // Option Actions
                $(document).on('change', '.flow-opt-action', function () {
                    const s = $(this).data('step');
                    const o = $(this).data('opt');
                    steps[s].options[o].action = $(this).val();
                    renderSteps();
                });
                $(document).on('change keyup', '.flow-opt-label', function () { steps[$(this).data('step')].options[$(this).data('opt')].label = $(this).val(); updateJson(); });
                $(document).on('change keyup', '.flow-opt-value', function () { steps[$(this).data('step')].options[$(this).data('opt')].value = $(this).val(); updateJson(); });
                $(document).on('change keyup', '.flow-opt-next', function () { steps[$(this).data('step')].options[$(this).data('opt')].next = $(this).val(); updateJson(); });

                $(document).on('click', '.remove-step', function () { steps.splice($(this).data('index'), 1); renderSteps(); });
                $(document).on('click', '.add-option', function () { steps[$(this).data('step')].options.push({ label: '', next: '', action: 'step' }); renderSteps(); });
                $(document).on('click', '.remove-opt', function () { steps[$(this).data('step')].options.splice($(this).data('opt'), 1); renderSteps(); });

            });
        </script>

        <div style="flex:1;">
            <h3>Existing Flows</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Keywords</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($flows):
                        foreach ($flows as $flow): ?>
                            <tr>
                                <td><?php echo esc_html($flow->title); ?></td>
                                <td><?php echo esc_html($flow->trigger_keywords); ?></td>
                                <td>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete this flow?');">
                                        <?php wp_nonce_field('nueva_flow_verify'); ?>
                                        <input type="hidden" name="nueva_flow_action" value="delete">
                                        <input type="hidden" name="flow_id" value="<?php echo $flow->id; ?>">
                                        <button type="submit" class="button button-small button-link-delete"
                                            style="color:#a00;">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="3">No flows found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>