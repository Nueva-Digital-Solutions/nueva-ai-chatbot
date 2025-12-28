<?php
global $wpdb;
$table_name = $wpdb->prefix . 'bua_chat_flows';

// Handle Save
if (isset($_POST['nueva_flow_action']) && check_admin_referer('nueva_flow_verify')) {
    $title = sanitize_text_field($_POST['flow_title']);
    $json = wp_unslash($_POST['flow_json']); // Allow JSON structure
    $keywords = sanitize_text_field($_POST['flow_keywords']);

    $wpdb->insert($table_name, [
        'title' => $title,
        'flow_json' => $json,
        'trigger_keywords' => $keywords,
        'is_active' => 1,
        'created_at' => current_time('mysql')
    ]);
    echo '<div class="notice notice-success"><p>Flow saved successfully.</p></div>';
}

$flows = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");
?>

<div class="wrap">
    <h1>Chat Flows</h1>
    <div style="margin-bottom: 20px;">
        <!-- Placeholder for Auto-Generate -->
        <button class="button button-secondary" disabled>Auto-Generate Flow (Coming Soon)</button>
    </div>

    <div style="display:flex; gap:20px;">
        <div style="flex:2;">
            <h3>Create New Flow (Visual Builder)</h3>
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
                    <p class="description">Define the conversation steps. The first step is always the starting point.
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
                        step.options.forEach((opt, optIndex) => {
                            optionsHtml += `
                        <div class="flow-option-row" style="display:flex; gap:10px; margin-bottom:5px;">
                            <input type="text" placeholder="Button Label" value="${opt.label}" data-step="${index}" data-opt="${optIndex}" class="flow-opt-label" style="flex:1;">
                            <input type="text" placeholder="Next Step ID" value="${opt.next}" data-step="${index}" data-opt="${optIndex}" class="flow-opt-next" style="width:100px;">
                            <button type="button" class="button remove-opt" data-step="${index}" data-opt="${optIndex}">&times;</button>
                        </div>
                    `;
                        });

                        const html = `
                    <div class="flow-step" data-index="${index}" style="border:1px solid #eee; padding:15px; margin-bottom:15px; background:#f9f9f9;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                            <strong>Step ID: <input type="text" value="${step.id}" class="step-id-input" data-index="${index}" style="width:100px; padding:2px;"></strong>
                            <button type="button" class="button-link-delete remove-step" data-index="${index}">Remove</button>
                        </div>
                        <label>Bot Message:</label>
                        <textarea class="widefat step-message" data-index="${index}" rows="2">${step.message}</textarea>
                        
                        <div style="margin-top:10px;">
                            <label>User Options (Buttons):</label>
                            <div class="step-options">${optionsHtml}</div>
                            <button type="button" class="button button-small add-option" data-step="${index}">+ Add Option</button>
                        </div>
                    </div>
                `;
                        $container.append(html);
                    });
                    updateJson();
                }

                // Add Step
                $('#add-flow-step').click(function () {
                    const id = 'step_' + Math.random().toString(36).substr(2, 5);
                    steps.push({ id: id, message: '', options: [] });
                    renderSteps();
                });

                // Event Delegation for Dynamic Inputs
                $(document).on('change keyup', '.step-id-input', function () {
                    steps[$(this).data('index')].id = $(this).val();
                    updateJson();
                });

                $(document).on('change keyup', '.step-message', function () {
                    steps[$(this).data('index')].message = $(this).val();
                    updateJson();
                });

                // Remove Step
                $(document).on('click', '.remove-step', function () {
                    steps.splice($(this).data('index'), 1);
                    renderSteps();
                });

                // Add Option
                $(document).on('click', '.add-option', function () {
                    steps[$(this).data('step')].options.push({ label: '', next: '' });
                    renderSteps();
                });

                // Update Option
                $(document).on('change keyup', '.flow-opt-label', function () {
                    steps[$(this).data('step')].options[$(this).data('opt')].label = $(this).val();
                    updateJson();
                });
                $(document).on('change keyup', '.flow-opt-next', function () {
                    steps[$(this).data('step')].options[$(this).data('opt')].next = $(this).val();
                    updateJson();
                });

                // Remove Option
                $(document).on('click', '.remove-opt', function () {
                    const stepIdx = $(this).data('step');
                    const optIdx = $(this).data('opt');
                    steps[stepIdx].options.splice(optIdx, 1);
                    renderSteps();
                });

                function updateJson() {
                    // Convert array to the map-like structure expected by the chatbot (or adjust chatbot to handle array)
                    // Current chatbot might expect simple ID -> Node mapping? 
                    // The prompt/logic probably parses JSON. Let's save as structure:
                    // { "start": "step_id_of_first", "nodes": { "step_id": { "message": "...", "options": [...] } } }

                    if (steps.length === 0) {
                        $('#flow_json_hidden').val('');
                        return;
                    }

                    const data = {
                        start: steps[0].id,
                        nodes: {}
                    };

                    steps.forEach(step => {
                        data.nodes[step.id] = {
                            message: step.message,
                            options: step.options
                        };
                    });

                    $('#flow_json_hidden').val(JSON.stringify(data));
                }

                // Init with one step
                $('#add-flow-step').click();
            });
        </script>

        <div style="flex:1;">
            <h3>Existing Flows</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Keywords</th>
                        <th>Active</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($flows):
                        foreach ($flows as $flow): ?>
                            <tr>
                                <td><?php echo $flow->title; ?></td>
                                <td><?php echo $flow->trigger_keywords; ?></td>
                                <td><?php echo $flow->is_active ? 'Yes' : 'No'; ?></td>
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