jQuery(function($){
    // initialize all elite sliders on the page
    $('.elite-slider-wrap').each(function(){
        var wrap = $(this);
        var settings = wrap.find('.swiper').closest('.elite-slider-wrap').data('settings') || {};
        try { settings = (typeof settings === 'string') ? JSON.parse(settings) : settings; } catch (e) {}
        var autoplay = settings.autoplay ? { delay: (settings.autoplay_speed||3)*1000, disableOnInteraction:false } : false;
        var options = {
            loop: true,
            slidesPerView: settings.images_per_slide || 1,
            spaceBetween: 10,
            autoplay: autoplay,
            pagination: { el: '.swiper-pagination', clickable: true },
            navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
        };
        var swiperEl = wrap.find('.swiper')[0];
        if(swiperEl) new Swiper(swiperEl, options);
    });
});
