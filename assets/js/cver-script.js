// CV Enhanced Review - Handles AJAX for review, voting, filtering, sorting, etc.

jQuery(document).ready(function($) {
    // Handle pagination clicks (exact copy from legacy custom-functions.php)
    $(document).on('click', '.woocommerce-pagination a, .page-numbers a', function(e) {
        e.preventDefault();
        
        var $this = $(this);
        var page = 1;
        
        // Get page number from data attribute or href
        if ($this.data('page')) {
            page = parseInt($this.data('page'));
        } else {
            var href = $this.attr('href');
            if (href) {
                var urlParams = new URLSearchParams(href.split('?')[1]);
                if (urlParams.has('cpage')) {
                    page = parseInt(urlParams.get('cpage'));
                } else if (urlParams.has('page')) {
                    page = parseInt(urlParams.get('page'));
                }
            }
        }
        
        // Get product ID from context
        var productId = $('#cver-reviews-wrap').data('product-id') || $('[name="add-to-cart"]').val();
        
        // Show loading overlay with animation inside commentlist
        $('.commentlist').append('<div class="reviews-loading-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.8); z-index: 999; display: flex; align-items: center; justify-content: center;"><div class="loading-spinner" style="width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #0073aa; border-radius: 50%; animation: spin 1s linear infinite;"></div></div>');
        
        // Use plugin's REST API for loading
        $.ajax({
            url: cver_ajax.rest_url || '/wp-json/cver/v1/reviews-pagination',
            type: 'GET',
            data: {
                product_id: productId,
                page: page,
                per_page: 4
            },
            success: function(response) {
                console.log('=== PAGINATION AJAX Response (REST) ===');
                console.log('Success:', response.success);
                console.log('HTML length:', response.data && response.data.html ? response.data.html.length : 0);
                console.log('HTML preview:', response.data && response.data.html ? response.data.html.substring(0, 500) : 'N/A');
                console.log('Target element exists:', $('#cver-reviews-ajax-area').length);
                console.log('============================');
                
                if (response.success && response.data && response.data.html) {
                    // Remove loading overlay
                    $('.reviews-loading-overlay').remove();
                    
                    // Check for duplicate IDs and remove them
                    var existingCount = $('#cver-reviews-ajax-area').length;
                    if (existingCount > 1) {
                        console.warn('PAGINATION (REST): Duplicate #cver-reviews-ajax-area found! Removing duplicates...', existingCount);
                        $('#cver-reviews-ajax-area').slice(1).remove();
                    }
                    
                    // Clean response HTML - remove any nested #cver-reviews-ajax-area
                    var cleanHtml = response.data.html.replace(/<div[^>]*id="cver-reviews-ajax-area"[^>]*>/gi, '').replace(/<\/div>\s*(?=<ol|<nav|<!--)/g, '');
                    
                    // Update only the AJAX area (not the summary)
                    $('#cver-reviews-ajax-area').first().html(cleanHtml);
                    console.log('Content replaced. New content length:', $('#cver-reviews-ajax-area').first().html().length);
                    console.log('Current #cver-reviews-ajax-area count:', $('#cver-reviews-ajax-area').length);
                    // Remove next/prev text from pagination
                    $('.woocommerce-pagination .prev').text('');
                    $('.woocommerce-pagination .next').text('');
                    
                    // Reset accordion max-height to accommodate new content
                    var $accordionContent = $('#cver-reviews-wrap').closest('.gb-accordion__content');
                    if ($accordionContent.length) {
                        // Temporarily remove max-height to measure content
                        $accordionContent.css('max-height', 'none');
                        var contentHeight = $accordionContent.outerHeight();
                        // Set new max-height based on content
                        $accordionContent.css('max-height', contentHeight + 'px');
                    }
                    
                    // Scroll to reviews section smoothly
                    $('html, body').animate({
                        scrollTop: $('#cver-reviews-wrap').offset().top - 100
                    }, 300);
                } else {
                    $('.reviews-loading-overlay').remove();
                    $('#cver-reviews-wrap').html('<p>Error loading reviews. Please try again.</p>');
                }
            },
            error: function(xhr, status, error) {
                // Fallback to admin-ajax if REST API fails
                $.ajax({
                    url: cver_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'cver_filter_reviews',
                        page: page,
                        product_id: productId,
                        per_page: 4,
                        nonce: cver_ajax.nonce
                    },
                    success: function(response) {
                        console.log('=== PAGINATION AJAX Response (Fallback admin-ajax) ===');
                        console.log('Success:', response.success);
                        console.log('HTML length:', response.data && response.data.html ? response.data.html.length : 0);
                        console.log('Target element exists:', $('#cver-reviews-ajax-area').length);
                        console.log('============================');
                        
                        if (response.success && response.data) {
                            // Remove loading overlay
                            $('.reviews-loading-overlay').remove();
                            
                            // Check for duplicate IDs and remove them
                            var existingCount = $('#cver-reviews-ajax-area').length;
                            if (existingCount > 1) {
                                console.warn('PAGINATION (Fallback): Duplicate #cver-reviews-ajax-area found! Removing duplicates...', existingCount);
                                $('#cver-reviews-ajax-area').slice(1).remove();
                            }
                            
                            var responseHtml = response.data.html || response.data;
                            // Clean response HTML - remove any nested #cver-reviews-ajax-area
                            var cleanHtml = responseHtml.replace(/<div[^>]*id="cver-reviews-ajax-area"[^>]*>/gi, '').replace(/<\/div>\s*(?=<ol|<nav|<!--)/g, '');
                            
                            // Update only the AJAX area (not the summary)
                            $('#cver-reviews-ajax-area').first().html(cleanHtml);
                            console.log('Content replaced. New content length:', $('#cver-reviews-ajax-area').first().html().length);
                            console.log('Current #cver-reviews-ajax-area count:', $('#cver-reviews-ajax-area').length);
                            // Remove next/prev text from pagination
                            $('.woocommerce-pagination .prev').text('');
                            $('.woocommerce-pagination .next').text('');
                            
                            // Reset accordion max-height to accommodate new content
                            var $accordionContent = $('#cver-reviews-wrap').closest('.gb-accordion__content');
                            if ($accordionContent.length) {
                                // Temporarily remove max-height to measure content
                                $accordionContent.css('max-height', 'none');
                                var contentHeight = $accordionContent.outerHeight();
                                // Set new max-height based on content
                                $accordionContent.css('max-height', contentHeight + 'px');
                            }
                            
                            // Scroll to reviews section smoothly
                            $('html, body').animate({
                                scrollTop: $('#cver-reviews-wrap').offset().top - 100
                            }, 300);
                        } else {
                            $('.reviews-loading-overlay').remove();
                            $('#cver-reviews-wrap').html('<p>Error loading reviews. Please try again.</p>');
                        }
                    },
                    error: function(xhr, status, error) {
                        $('.reviews-loading-overlay').remove();
                        $('#cver-reviews-wrap').html('<p>Error loading reviews. Please try again.</p>');
                    }
                });
            }
        });
    });

    // Star filter click (enhanced feature)
    $(document).on('click', '.cver-star-row', function(){
        var star = $(this).data('star');
        var productId = $('#cver-reviews-wrap').data('product-id') || $('[name="add-to-cart"]').val();
        $('.commentlist').append('<div class="reviews-loading-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.8); z-index: 999; display: flex; align-items: center; justify-content: center;"><div class="loading-spinner" style="width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #0073aa; border-radius: 50%; animation: spin 1s linear infinite;"></div></div>');
        $.post(cver_ajax.ajax_url, {
            action: 'cver_filter_reviews',
            product_id: productId,
            page: 1,
            per_page: 4,
            star: star,
            sort: 'newest',
            nonce: cver_ajax.nonce
        }, function(res){
            $('.reviews-loading-overlay').remove();
            if (res.success && res.data && res.data.html) {
                $('#cver-reviews-ajax-area').html(res.data.html);
                $('.cver-clear-filter-area').show();
            }
        });
    });

    // Clear filter
    $(document).on('click', '#cver-clear-filter', function(){
        var productId = $('#cver-reviews-wrap').data('product-id') || $('[name="add-to-cart"]').val();
        $('.commentlist').append('<div class="reviews-loading-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.8); z-index: 999; display: flex; align-items: center; justify-content: center;"><div class="loading-spinner" style="width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #0073aa; border-radius: 50%; animation: spin 1s linear infinite;"></div></div>');
        $.post(cver_ajax.ajax_url, {
            action: 'cver_filter_reviews',
            product_id: productId,
            page: 1,
            per_page: 4,
            nonce: cver_ajax.nonce
        }, function(res){
            $('.reviews-loading-overlay').remove();
            if (res.success && res.data && res.data.html) {
                $('#cver-reviews-ajax-area').html(res.data.html);
                $('.cver-clear-filter-area').hide();
            }
        });
    });

    // Custom filter dropdown toggle
    $(document).on('click', '#cver-filter-selected', function(e){
        e.stopPropagation();
        $('.cver-filter-dropdown-custom').toggleClass('open');
        $('.cver-sort-dropdown-custom').removeClass('open');
    });
    
    // Custom sort dropdown toggle
    $(document).on('click', '#cver-sort-selected', function(e){
        e.stopPropagation();
        $('.cver-sort-dropdown-custom').toggleClass('open');
        $('.cver-filter-dropdown-custom').removeClass('open');
    });
    
    // Close dropdowns when clicking outside
    $(document).on('click', function(e){
        if(!$(e.target).closest('.cver-filter-dropdown-custom').length){
            $('.cver-filter-dropdown-custom').removeClass('open');
        }
        if(!$(e.target).closest('.cver-sort-dropdown-custom').length){
            $('.cver-sort-dropdown-custom').removeClass('open');
        }
    });
    
    // Filter option selection
    $(document).on('click', '.cver-filter-option', function(){
        var star = $(this).data('value');
        var starText = $(this).find('.cver-filter-stars').text().trim() || 'All Stars';
        var selectedText = star ? 'Filter: ' + starText : 'Filter by rating';
        $('#cver-filter-selected').text(selectedText);
        $('#cver-filter-dropdown').val(star);
        $('.cver-filter-dropdown-custom').removeClass('open');
        $('.cver-filter-option').removeClass('active');
        $(this).addClass('active');
        
        // Store selected text for after AJAX
        $('#cver-filter-selected').data('selected-text', selectedText);
        
        // Trigger AJAX reload
        var sort = $('#cver-sort-dropdown').val() || 'newest';
        var productId = $('#cver-reviews-wrap').data('product-id') || $('[name="add-to-cart"]').val();
        
        console.log('=== FILTER AJAX Request ===');
        console.log('Star:', star, 'Type:', typeof star);
        console.log('Sort:', sort);
        console.log('Product ID:', productId);
        console.log('============================');
        
        $('.commentlist').append('<div class="reviews-loading-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.8); z-index: 999; display: flex; align-items: center; justify-content: center;"><div class="loading-spinner" style="width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #0073aa; border-radius: 50%; animation: spin 1s linear infinite;"></div></div>');
        $.post(cver_ajax.ajax_url, {
            action: 'cver_filter_reviews',
            product_id: productId,
            page: 1,
            per_page: 4,
            star: star,
            sort: sort,
            nonce: cver_ajax.nonce
        }, function(res){
            console.log('=== FILTER AJAX Response ===');
            console.log('Success:', res.success);
            console.log('HTML length:', res.data && res.data.html ? res.data.html.length : 0);
            console.log('HTML preview:', res.data && res.data.html ? res.data.html.substring(0, 500) : 'N/A');
            console.log('Target element exists:', $('#cver-reviews-ajax-area').length);
            console.log('============================');
            
            $('.reviews-loading-overlay').remove();
            if (res.success && res.data && res.data.html) {
                // Check for duplicate IDs and remove them
                var existingCount = $('#cver-reviews-ajax-area').length;
                if (existingCount > 1) {
                    console.warn('FILTER: Duplicate #cver-reviews-ajax-area found! Removing duplicates...', existingCount);
                    $('#cver-reviews-ajax-area').slice(1).remove();
                }
                
                // Clean response HTML - remove any nested #cver-reviews-ajax-area
                var cleanHtml = res.data.html.replace(/<div[^>]*id="cver-reviews-ajax-area"[^>]*>/gi, '').replace(/<\/div>\s*(?=<ol|<nav|<!--)/g, '');
                
                $('#cver-reviews-ajax-area').first().html(cleanHtml);
                console.log('Content replaced. New content length:', $('#cver-reviews-ajax-area').first().html().length);
                console.log('Current #cver-reviews-ajax-area count:', $('#cver-reviews-ajax-area').length);
                // Restore selected text after AJAX
                var restoredText = $('#cver-filter-selected').data('selected-text');
                if (restoredText) {
                    $('#cver-filter-selected').text(restoredText);
                }
            }
        });
    });

    // Sort option selection
    $(document).on('click', '.cver-sort-option', function(){
        var sort = $(this).data('value');
        var sortText = $(this).text().trim();
        var selectedText = 'Sort by: ' + sortText;
        $('#cver-sort-selected').text(selectedText);
        $('#cver-sort-dropdown').val(sort);
        $('.cver-sort-dropdown-custom').removeClass('open');
        $('.cver-sort-option').removeClass('active');
        $(this).addClass('active');
        
        // Store selected text for after AJAX
        $('#cver-sort-selected').data('selected-text', selectedText);
        
        // Trigger AJAX reload
        var star = $('#cver-filter-dropdown').val() || '';
        var productId = $('#cver-reviews-wrap').data('product-id') || $('[name="add-to-cart"]').val();
        $('.commentlist').append('<div class="reviews-loading-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.8); z-index: 999; display: flex; align-items: center; justify-content: center;"><div class="loading-spinner" style="width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #0073aa; border-radius: 50%; animation: spin 1s linear infinite;"></div></div>');
        $.post(cver_ajax.ajax_url, {
            action: 'cver_filter_reviews',
            product_id: productId,
            page: 1,
            per_page: 4,
            star: star,
            sort: sort,
            nonce: cver_ajax.nonce
        }, function(res){
            console.log('=== SORT AJAX Response ===');
            console.log('Success:', res.success);
            console.log('HTML length:', res.data && res.data.html ? res.data.html.length : 0);
            console.log('HTML preview:', res.data && res.data.html ? res.data.html.substring(0, 500) : 'N/A');
            console.log('Target element exists:', $('#cver-reviews-ajax-area').length);
            console.log('============================');
            
            $('.reviews-loading-overlay').remove();
            if (res.success && res.data && res.data.html) {
                // Check for duplicate IDs and remove them
                var existingCount = $('#cver-reviews-ajax-area').length;
                if (existingCount > 1) {
                    console.warn('SORT: Duplicate #cver-reviews-ajax-area found! Removing duplicates...', existingCount);
                    $('#cver-reviews-ajax-area').slice(1).remove();
                }
                
                // Clean response HTML - remove any nested #cver-reviews-ajax-area
                var cleanHtml = res.data.html.replace(/<div[^>]*id="cver-reviews-ajax-area"[^>]*>/gi, '').replace(/<\/div>\s*(?=<ol|<nav|<!--)/g, '');
                
                $('#cver-reviews-ajax-area').first().html(cleanHtml);
                console.log('Content replaced. New content length:', $('#cver-reviews-ajax-area').first().html().length);
                console.log('Current #cver-reviews-ajax-area count:', $('#cver-reviews-ajax-area').length);
                // Restore selected text after AJAX
                var restoredText = $('#cver-sort-selected').data('selected-text');
                if (restoredText) {
                    $('#cver-sort-selected').text(restoredText);
                }
            }
        });
    });

    // Helpful vote
    $(document).on('click', '.cver-helpful-btn', function(e){
        e.preventDefault();
        const commentId = $(this).data('comment-id');
        const value = $(this).data('value');
        const $btn = $(this);
        $btn.prop('disabled', true);
        $.post(cver_ajax.ajax_url, {
            action: 'cver_helpful_vote',
            comment_id: commentId,
            value: value,
            nonce: cver_ajax.nonce
        }, function(res){
            if (res.success && res.data) {
                $btn.closest('.cver-helpful-area').find('.cver-helpful-up .cver-count').text(res.data.up);
                $btn.closest('.cver-helpful-area').find('.cver-helpful-down .cver-count').text(res.data.down);
            }
            $btn.prop('disabled', false);
        });
    });
});
