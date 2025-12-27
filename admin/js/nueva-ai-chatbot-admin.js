jQuery(document).ready(function ($) {
    // Tab Switching Logic
    $('.nav-tab').click(function (e) {
        e.preventDefault();

        // Remove active class from all tabs
        $('.nav-tab').removeClass('nav-tab-active');

        // Add active class to clicked tab
        $(this).addClass('nav-tab-active');

        // Hide all tab content
        $('.tab-content').hide();

        // Show target tab content
        var activeTab = $(this).attr('href');
        $(activeTab).fadeIn();

        // Store active tab in URL hash (optional, for persistency)
        // window.location.hash = activeTab;
    });

    // Auto-select tab if hash exists
    var hash = window.location.hash;
    if (hash && $(hash).length > 0) {
        $('.nav-tab[href="' + hash + '"]').click();
    }

    // Color Picker
    $('.my-color-field').wpColorPicker();

    // Media Uploader
    var file_frame;
    $('#upload_image_button').on('click', function (event) {
        event.preventDefault();
        if (file_frame) {
            file_frame.open();
            return;
        }
        file_frame = wp.media.frames.file_frame = wp.media({
            title: 'Select Profile Image',
            button: {
                text: 'Use this image'
            },
            multiple: false
        });
        file_frame.on('select', function () {
            var attachment = file_frame.state().get('selection').first().toJSON();
            $('#nueva_profile_image').val(attachment.url);
        });
        file_frame.open();
    });
});
