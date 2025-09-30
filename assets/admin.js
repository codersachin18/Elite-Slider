jQuery(function($){
    // Media picker
    var frame;
    $('.elite-media-add').on('click', function(e){
        e.preventDefault();
        if(frame) frame.open();
        frame = wp.media({title:'Select Images', multiple:true, library:{type:'image'}});
        frame.on('select', function(){
            var selection = frame.state().get('selection').toArray();
            selection.forEach(function(attachment){
                var id = attachment.id;
                var thumb = attachment.attributes.sizes && attachment.attributes.sizes.thumbnail ? attachment.attributes.sizes.thumbnail.url : attachment.attributes.url;
                $('#elite-images-list').append('<div class="elite-image-item" data-id="'+id+'"><img src="'+thumb+'" width="110"><a href="#" class="elite-remove-image">Remove</a><input type="hidden" name="elite_images[]" value="'+id+'"></div>');
            });
        });
        frame.open();
    });

    // remove image
    $(document).on('click', '.elite-remove-image', function(e){
        e.preventDefault();
        $(this).closest('.elite-image-item').remove();
    });

    // Preview button: collect data and call AJAX preview
    $('#elite-preview-btn').on('click', function(){
        var images = $('input[name="elite_images[]"]').map(function(){return $(this).val();}).get();
        var data = {
            action:'elite_slider_action',
            act:'save_slider',
            nonce: EliteAjax.nonce,
            title: $('input[name="elite_title"]').val() || 'Preview',
            images: images,
            images_per_slide: $('input[name="images_per_slide"]').val() || 1,
            height: $('input[name="height"]').val() || '400px',
            object_fit: $('select[name="object_fit"]').val(),
            autoplay: $('input[name="autoplay"]').is(':checked') ? 1 : 0,
            autoplay_speed: $('input[name="autoplay_speed"]').val() || 3,
            arrows: $('input[name="arrows"]').is(':checked') ? 1 : 0,
            pagination: $('input[name="pagination"]').is(':checked') ? 1 : 0,
            edit_id: $('input[name="elite_edit_id"]').val()
        };
        $.post(EliteAjax.ajax_url, data, function(res){
            if(res.success){
                // show preview by using shortcode preview ajax
                $.post(EliteAjax.ajax_url, {action:'elite_slider_action', act:'preview', nonce:EliteAjax.nonce, id: res.data.id}, function(pre){
                    if(pre.success){
                        var w = window.open('', 'Preview', 'width=900,height=600');
                        w.document.write(pre.data.html);
                    }
                });
            } else {
                alert('Save failed');
            }
        });
    });

    // handle form submit (Publish / Update)
    $('form').on('submit', function(e){
        var btn = $(document.activeElement);
        if(btn.attr('name') === 'elite_publish'){
            e.preventDefault();

 var title = $('input[name="elite_title"]').val().trim();
        if(title === ''){
            alert('Please enter a slider title.');
            $('input[name="elite_title"]').focus();
            return;
        }

        // ✅ Minimum images validation
        var minImages = parseInt($('.elite-card[data-min-images]').data('min-images')) || 1;
        var selectedImages = $('input[name="elite_images[]"]').length;
        if(selectedImages < minImages){
            alert('Please select at least '+minImages+' images for the slider.');
            return;
        }



            var images = $('input[name="elite_images[]"]').map(function(){return $(this).val();}).get();
            var data = {
                action:'elite_slider_action',
                act:'save_slider',
                nonce: EliteAjax.nonce,
                title: $('input[name="elite_title"]').val() || 'Untitled',
                images: images,
                images_per_slide: $('input[name="images_per_slide"]').val() || 1,
                height: $('input[name="height"]').val() || '400px',
                object_fit: $('select[name="object_fit"]').val(),
                autoplay: $('input[name="autoplay"]').is(':checked') ? 1 : 0,
                autoplay_speed: $('input[name="autoplay_speed"]').val() || 3,
                arrows: $('input[name="arrows"]').is(':checked') ? 1 : 0,
                pagination: $('input[name="pagination"]').is(':checked') ? 1 : 0,
                edit_id: $('input[name="elite_edit_id"]').val()
            };
            $.post(EliteAjax.ajax_url, data, function(res){
                if(res.success){
                   // Create modal
var modal = document.createElement('div');
modal.id = 'elite-publish-modal';
modal.innerHTML = `
    <div class="elite-modal-overlay"></div>
    <div class="elite-modal-box">
        <h2>Slider Published 🎉</h2>
        <p>Use this shortcode:</p>
        <div class="elite-shortcode-box">
            <code>${res.data.shortcode}</code>
            <button id="elite-copy-btn" class="button button-primary">Copy</button>
        </div>
        <button id="elite-close-btn" class="button">Close</button>
    </div>
`;
document.body.appendChild(modal);

// Copy button
document.getElementById('elite-copy-btn').addEventListener('click', function(){
    navigator.clipboard.writeText(res.data.shortcode);
    this.textContent = 'Copied!';
});

// Close button → go back to dashboard
document.getElementById('elite-close-btn').addEventListener('click', function(){
    window.location.href = 'admin.php?page=elite-slider';
});

                } else alert('Error');
            });
        }
    });

    // dashboard actions: copy, toggle, delete, duplicate, preview
    $('.elite-copy-shortcode').on('click', function(e){ e.preventDefault(); var sc = $(this).data('shortcode'); navigator.clipboard.writeText(sc); alert('Shortcode copied'); });
    $('.elite-toggle').on('click', function(e){ e.preventDefault(); var id = $(this).data('id'); $.post(EliteAjax.ajax_url, {action:'elite_slider_action', act:'toggle_disable', id:id, nonce:EliteAjax.nonce}, function(){ location.reload(); }); });
    $('.elite-delete').on('click', function(e){ e.preventDefault(); if(confirm('Delete slider?')){ var id=$(this).data('id'); $.post(EliteAjax.ajax_url, {action:'elite_slider_action', act:'delete_slider', id:id, nonce:EliteAjax.nonce}, function(){ location.reload(); }); }});
    $('.elite-duplicate').on('click', function(e){ e.preventDefault(); var id=$(this).data('id'); $.post(EliteAjax.ajax_url, {action:'elite_slider_action', act:'duplicate', id:id, nonce:EliteAjax.nonce}, function(res){ if(res.success) location.reload(); }); });
    $('.elite-preview').on('click', function(e){ e.preventDefault(); var id=$(this).data('id'); $.post(EliteAjax.ajax_url, {action:'elite_slider_action', act:'preview', id:id, nonce:EliteAjax.nonce}, function(res){ if(res.success){ var w=window.open('','Preview','width=900,height=600'); w.document.write(res.data.html); } }); });
});
// admin.js

function initPreviewSlider() {
    const previewEl = document.querySelector('.elite-slider-preview .swiper');
    if (previewEl) {
        new Swiper(previewEl, {
            slidesPerView: parseInt(previewEl.dataset.slides) || 1,
            autoplay: previewEl.dataset.autoplay === "true" ? {
                delay: parseInt(previewEl.dataset.speed) * 1000 || 3000,
                disableOnInteraction: false,
            } : false,
            navigation: previewEl.dataset.navigation === "true" ? {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            } : false,
            pagination: previewEl.dataset.pagination === "true" ? {
                el: '.swiper-pagination',
                clickable: true,
            } : false,
        });
    }
}

// Call after clicking preview button
document.addEventListener("DOMContentLoaded", function(){
    const previewBtn = document.getElementById('preview-btn');
    if(previewBtn){
        previewBtn.addEventListener('click', function() {
            setTimeout(initPreviewSlider, 300); 
        });
    }
});
