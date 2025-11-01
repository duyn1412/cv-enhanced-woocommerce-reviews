<?php
if (!defined('ABSPATH')) exit;

class CVER_Core {
    public function __construct() {
        // Output summary and chart at shortcode render
        add_action('cver_render_summary', [$this, 'render_rating_summary'], 10, 1);
        add_action('cver_render_distribution', [$this, 'render_star_distribution'], 10, 1);

        // Setup AJAX for review filtering and voting
        add_action('wp_ajax_cver_filter_reviews', [$this, 'handle_filter_reviews']);
        add_action('wp_ajax_nopriv_cver_filter_reviews', [$this, 'handle_filter_reviews']);
        add_action('wp_ajax_cver_helpful_vote', [$this, 'handle_helpful_vote']);
        add_action('wp_ajax_nopriv_cver_helpful_vote', [$this, 'handle_helpful_vote']);

        // REST and admin-ajax endpoints for review pagination
        add_action('wp_ajax_cver_load_reviews', [$this, 'ajax_load_reviews']);
        add_action('wp_ajax_nopriv_cver_load_reviews', [$this, 'ajax_load_reviews']);
        add_action('rest_api_init', function() {
            register_rest_route('cver/v1', '/reviews-pagination', array(
                'methods' => 'GET',
                'callback' => [$this, 'rest_load_reviews'],
                'permission_callback' => '__return_true',
                'args' => array(
                    'product_id' => ['required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint'],
                    'page' => ['default' => 1, 'type' => 'integer', 'sanitize_callback' => 'absint'],
                    'per_page' => ['default' => 4, 'type' => 'integer', 'sanitize_callback' => 'absint'],
                )
            ));
        });
    }

    /**
     * Render average rating summary for product
     */
    public function render_rating_summary($product_id = 0) {
        if (!$product_id) $product_id = get_the_ID();
        $stats = $this->get_review_stats($product_id);
        ?>
        <div class="cver-summary">
            <div class="cver-average-rating">
                <span class="cver-avg-score"><?php echo esc_html($stats['average']); ?>/5</span>
                <span class="cver-rating-stars" aria-label="Average rating: <?php echo esc_attr($stats['average']); ?> stars">
                    <?php for ($i=1; $i<=5; $i++): ?>
                        <span class="cver-star <?php echo ($i <= round($stats['average'])) ? 'active' : ''; ?>">★</span>
                    <?php endfor; ?>
                </span>
                <span class="cver-total-reviews">Based on <?php echo esc_html($stats['total']); ?> reviews</span>
            </div>
        </div>
        <?php
    }

    /**
     * Render star distribution (bar chart + clickable filter)
     */
    public function render_star_distribution($product_id = 0) {
        if (!$product_id) $product_id = get_the_ID();
        $stats = $this->get_review_stats($product_id);
        ?>
        <div class="cver-star-distribution">
            <?php for($star=5; $star>=1; $star--): 
                $count = $stats['distribution'][$star]; 
                $percent = $stats['total'] ? round(($count/$stats['total'])*100) : 0;
            ?>
                <div class="cver-star-row" data-star="<?php echo $star; ?>">
                    <span class="cver-row-star-label"><?php echo $star; ?> stars</span>
                    <span class="cver-row-bar" style="width:<?php echo $percent; ?>%"><span class="cver-row-bar-inner"></span></span>
                    <span class="cver-row-percent"><?php echo $percent; ?>%</span>
                    <span class="cver-row-count"><?php echo $count; ?></span>
                </div>
            <?php endfor; ?>
        </div>
        <div class="cver-clear-filter-area" style="display:none;">
            <button type="button" id="cver-clear-filter">Clear Filter</button>
        </div>
        <?php
    }
    
    /**
     * Fetch review stats (average, total, breakdown)
     */
    public function get_review_stats($product_id) {
        $reviews = get_comments([
            'post_id' => $product_id,
            'status' => 'approve',
            'type' => 'review',
            'number' => 0
        ]);
        $sum = 0; $total = count($reviews);
        $distribution = [1=>0,2=>0,3=>0,4=>0,5=>0];
        foreach($reviews as $review) {
            $rating = intval(get_comment_meta($review->comment_ID, 'rating', true));
            if ($rating >= 1 && $rating <= 5) {
                $sum += $rating;
                $distribution[$rating]++;
            }
        }
        $average = $total ? round($sum/$total, 2) : 0;
        return [
            'average' => $average,
            'total' => $total,
            'distribution' => $distribution,
        ];
    }

    /**
    * Render paginated reviews (called in shortcode & AJAX)
    */
    public function get_reviews_with_pagination($args = array()) {
        $defaults = array(
            'product_id' => get_the_ID(),
            'per_page' => 4,
            'page' => 1,
            'star' => null,
            'sort' => 'newest'
        );
        $a = wp_parse_args($args, $defaults);
        $comments_per_page = intval($a['per_page']);
        $current_page = intval($a['page']);
        $product_id = intval($a['product_id']);
        $star = $a['star'];
        $sort = $a['sort'];
        
        // Debug log
        error_log('CVER get_reviews_with_pagination - Star: ' . var_export($star, true) . ', Type: ' . gettype($star));
        
        $query = [
            'post_id' => $product_id,
            'status' => 'approve',
            'type' => 'review',
            'number' => $comments_per_page,
            'offset' => ($current_page - 1) * $comments_per_page
        ];
        // Filter by star - check if star is not null and not empty string
        if ($star !== null && $star !== '' && $star > 0) {
            $query['meta_query'] = [
                ['key' => 'rating', 'value' => intval($star), 'compare' => '=', 'type' => 'NUMERIC']
            ];
            error_log('CVER - Applied star filter: ' . intval($star));
        }
        // Sort
        switch ($sort) {
            case 'highest': $query['orderby'] = 'meta_value_num'; $query['meta_key'] = 'rating'; $query['order'] = 'DESC'; break;
            case 'lowest': $query['orderby'] = 'meta_value_num'; $query['meta_key'] = 'rating'; $query['order'] = 'ASC'; break;
            case 'helpful': $query['orderby'] = 'meta_value_num'; $query['meta_key'] = 'cver_helpful_score'; $query['order'] = 'DESC'; break;
            default: $query['orderby'] = 'comment_date'; $query['order'] = 'DESC'; break;
        }
        $total_comments_query = [
            'post_id' => $product_id,
            'status' => 'approve',
            'type' => 'review',
            'count' => true
        ];
        if ($star) $total_comments_query['meta_query'] = [['key'=>'rating','value'=>$star,'compare'=>'=']];
        $total_comments = get_comments($total_comments_query);
        $total_pages = ceil($total_comments/$comments_per_page);
        $comments = get_comments($query);
        ob_start();
        // Only render controls on first load (not AJAX)
        $render_controls = !isset($args['skip_controls']) || !$args['skip_controls'];
        
        // Render title and average rating FIRST (outside AJAX reload area)
        if ($render_controls) {
            echo '<div id="reviews" class="woocommerce-Reviews">';
            echo '<div id="comments">';
            echo '<h2 class="woocommerce-Reviews-title">' . esc_html($total_comments) . ' reviews for <span>' . esc_html(get_the_title($product_id)) . '</span></h2>';
            
            // Average rating with WooCommerce star icons
            $stats = $this->get_review_stats($product_id);
            if ($stats['total'] > 0) {
                echo '<div class="cver-average-rating">';
                echo '<div class="star-rating" role="img" aria-label="Rated '.esc_attr($stats['average']).' out of 5">';
                echo '<span style="width:'.( ($stats['average'] / 5) * 100 ).'%">Rated <strong class="rating">'.esc_html($stats['average']).'</strong> out of 5</span>';
                echo '</div>';
                echo '<span class="cver-average-number">'.number_format($stats['average'], 1).' average rating</span>';
                echo '</div>';
            }
            echo '<!-- CVER_HEADER_END -->';
        }
        
        // Render Filter & Sort UI AFTER header (outside AJAX reload area)
        if ($render_controls) {
            // Calculate star distribution for filter UI
            $all_reviews = get_comments(['post_id'=>$product_id,'status'=>'approve','type'=>'review','number'=>0]);
            $star_counts = [1=>0,2=>0,3=>0,4=>0,5=>0];
            foreach($all_reviews as $rev) {
                $r = intval(get_comment_meta($rev->comment_ID,'rating',true));
                if($r>=1 && $r<=5) $star_counts[$r]++;
            }
            $total_reviews = count($all_reviews);
            
            // Filter & Sort UI
            echo '<div class="cver-controls-wrapper">';
            echo '<div class="cver-filter-wrapper">';
            echo '<svg viewBox="0 0 330 512" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="1em" height="1em"><path d="M305.913 197.085c0 2.266-1.133 4.815-2.833 6.514L171.087 335.593c-1.7 1.7-4.249 2.832-6.515 2.832s-4.815-1.133-6.515-2.832L26.064 203.599c-1.7-1.7-2.832-4.248-2.832-6.514s1.132-4.816 2.832-6.515l14.162-14.163c1.7-1.699 3.966-2.832 6.515-2.832 2.266 0 4.815 1.133 6.515 2.832l111.316 111.317 111.316-111.317c1.7-1.699 4.249-2.832 6.515-2.832s4.815 1.133 6.515 2.832l14.162 14.163c1.7 1.7 2.833 4.249 2.833 6.515z"></path></svg><div class="cver-filter-dropdown-custom">';
            $selected_text = $star ? 'Filter: '.$star.' Star'.($star>1?'s':'') : 'Filter by rating';
            echo '<div class="cver-filter-selected" id="cver-filter-selected" data-selected-text="'.esc_attr($selected_text).'">'.$selected_text.'</div>';
            echo '<div class="cver-filter-options" id="cver-filter-options">';
            echo '<div class="cver-filter-option'.($star==''?' active':'').'" data-value="">All Stars</div>';
            for($s=5;$s>=1;$s--){
                $count = $star_counts[$s];
                $percent = $total_reviews ? round(($count/$total_reviews)*100) : 0;
                echo '<div class="cver-filter-option'.($star==$s?' active':'').'" data-value="'.$s.'">';
                echo '<span class="cver-filter-percent">'.$percent.'%</span>';
                echo '<span class="cver-filter-bar"><span class="cver-filter-bar-fill" style="width:'.$percent.'%"></span></span>';
                echo '<span class="cver-filter-stars">'.$s.' Star'.($s>1?'s':'').'</span>';
                echo '</div>';
            }
            echo '</div></div>';
            echo '<input type="hidden" id="cver-filter-dropdown" value="'.esc_attr($star).'">';
            echo '</div>';
            echo '<div class="cver-sorting-wrapper">';
            echo '<div class="cver-sort-dropdown-custom">';
            $sort_labels = [
                'newest' => 'Newest',
                'highest' => 'Highest Rating',
                'lowest' => 'Lowest Rating',
                'helpful' => 'Most Helpful'
            ];
            $selected_sort_text = 'Sort by: '.($sort_labels[$sort] ?? 'Newest');
            echo '<div class="cver-sort-selected" id="cver-sort-selected" data-selected-text="'.esc_attr($selected_sort_text).'">'.$selected_sort_text.'<svg viewBox="0 0 330 512" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="1em" height="1em"><path d="M305.913 197.085c0 2.266-1.133 4.815-2.833 6.514L171.087 335.593c-1.7 1.7-4.249 2.832-6.515 2.832s-4.815-1.133-6.515-2.832L26.064 203.599c-1.7-1.7-2.832-4.248-2.832-6.514s1.132-4.816 2.832-6.515l14.162-14.163c1.7-1.699 3.966-2.832 6.515-2.832 2.266 0 4.815 1.133 6.515 2.832l111.316 111.317 111.316-111.317c1.7-1.699 4.249-2.832 6.515-2.832s4.815 1.133 6.515 2.832l14.162 14.163c1.7 1.7 2.833 4.249 2.833 6.515z"></path></svg></div>';
            echo '<div class="cver-sort-options" id="cver-sort-options">';
            foreach($sort_labels as $value => $label) {
                $active = ($sort == $value) ? ' active' : '';
                echo '<div class="cver-sort-option'.$active.'" data-value="'.$value.'">'.$label.'</div>';
            }
            echo '</div></div>';
            echo '<input type="hidden" id="cver-sort-dropdown" value="'.esc_attr($sort).'">';
            echo '</div>';
            echo '</div>';
            echo '<!-- CVER_CONTROLS_END -->';
        }
        
        echo '<ol class="commentlist">';
        global $comment, $comment_depth, $comment_parent;
        $comment_depth = 1;
        $comment_parent = 0;
        if ($comments) {
            foreach ($comments as $comment) {
                $comment_depth = 1;
                $comment_parent = 0;
                wc_get_template('single-product/review.php', array('comment' => $comment));
                // Add helpful voting below each review
                $up = (int)get_comment_meta($comment->comment_ID, 'cver_helpful_up', true);
                $down = (int)get_comment_meta($comment->comment_ID, 'cver_helpful_down', true);
                echo '<div class="cver-helpful-area">';
                echo '<span class="cver-helpful-text">Helpful?</span>';
                echo '<button class="cver-helpful-btn cver-helpful-up" data-comment-id="'.$comment->comment_ID.'" data-value="up"><span class="cver-icon-up">👍</span> <span class="cver-count">'.$up.'</span></button>';
                echo '<button class="cver-helpful-btn cver-helpful-down" data-comment-id="'.$comment->comment_ID.'" data-value="down"><span class="cver-icon-down">👎</span> <span class="cver-count">'.$down.'</span></button>';
                echo '</div>';
            }
        }
        echo '</ol>';
        if (!wp_is_mobile()) {
            echo '<div id="review_form_wrapper"><div id="review_form">';
            comment_form();
            echo '</div></div>';
        }
        // --- Pagination (custom, identical to original code) ---
        if ($total_pages > 1) {
            echo '<nav class="woocommerce-pagination">';
            echo '<ul class="page-numbers">';
            $start_num = max(1, $current_page - 1);
            $end_num = min($total_pages, $current_page + 1);
            if ($current_page <= 2) {
                $start_num = 1;
                $end_num = min(3, $total_pages);
            }
            // Show 3 numbers around current
            for ($i = $start_num; $i <= $end_num; $i++) {
                if ($i == $current_page) {
                    echo '<li><span class="page-numbers current">'.$i.'</span></li>';
                } else {
                    $url = add_query_arg('cpage', $i);
                    echo '<li><a class="page-numbers" href="'.$url.'">'.$i.'</a></li>';
                }
            }
            // Dots for gap
            if ($end_num < $total_pages - 1) {
                echo '<li><span class="page-numbers dots">…</span></li>';
            }
            // Last 2 pages
            if ($total_pages > 3) {
                for ($i = max($end_num + 1, $total_pages - 1); $i <= $total_pages; $i++) {
                    if ($i == $current_page) {
                        echo '<li><span class="page-numbers current">'.$i.'</span></li>';
                    } else {
                        $url = add_query_arg('cpage', $i);
                        echo '<li><a class="page-numbers" href="'.$url.'">'.$i.'</a></li>';
                    }
                }
            }
            echo '</ul>';
            echo '</nav>';
        }
        // Close wrapper divs only if we opened them (first load)
        if ($render_controls) {
            echo '</div>'; // Close #comments
            echo '</div>'; // Close #reviews
        }
        return ob_get_clean();
    }

    // AJAX for loading reviews (admin-ajax)
    public function ajax_load_reviews() {
        $product_id = intval($_POST['product_id'] ?? 0);
        $page = intval($_POST['page'] ?? 1);
        $per_page = intval($_POST['per_page'] ?? 4);
        $html = $this->get_reviews_with_pagination([
            'product_id' => $product_id,
            'per_page' => $per_page,
            'page' => $page,
            'skip_controls' => true
        ]);
        wp_send_json_success(['html' => $html, 'page' => $page]);
    }

    // AJAX for REST API
    public function rest_load_reviews($request) {
        $args = [
            'product_id' => $request->get_param('product_id'),
            'page' => $request->get_param('page'),
            'per_page' => $request->get_param('per_page'),
            'skip_controls' => true
        ];
        $html = $this->get_reviews_with_pagination($args);
        return rest_ensure_response([
            'success' => true,
            'data' => [
                'html' => $html,
                'page' => $args['page']
            ]
        ]);
    }

    /**
     * Shortcode handler for [cv_woo_reviews]
     *
     * @param array $atts
     * @return string
     */
    public function shortcode_review_container($atts) {
        if (!is_product()) return '';
        $product_id = get_the_ID();
        ob_start();
        echo '<div id="cver-reviews-wrap" data-product-id="'.esc_attr($product_id).'">';
        // Get initial reviews with controls (average rating now inside reviews area)
        $initial_html = $this->get_reviews_with_pagination([
            'product_id' => $product_id,
            'per_page' => 4,
            'page' => 1,
        ]);
        // Split header, controls and reviews using HTML comment markers
        // Structure: Header (title + avg rating) → Controls (filter/sort) → Reviews (AJAX area) → Close divs
        if (strpos($initial_html, '<!-- CVER_HEADER_END -->') !== false && strpos($initial_html, '<!-- CVER_CONTROLS_END -->') !== false) {
            // Split by header marker first
            $header_parts = explode('<!-- CVER_HEADER_END -->', $initial_html, 2);
            echo $header_parts[0]; // Header (title + avg rating + open divs) outside AJAX
            echo '<!-- CVER_HEADER_END -->';
            
            // Split remaining by controls marker
            $controls_parts = explode('<!-- CVER_CONTROLS_END -->', $header_parts[1], 2);
            echo $controls_parts[0]; // Controls outside AJAX area
            echo '<!-- CVER_CONTROLS_END -->';
            
            // Extract close divs from the end of reviews section
            $reviews_content = $controls_parts[1];
            // Remove close divs from reviews content (they will be added outside AJAX area)
            $reviews_content = preg_replace('/<\/div>\s*<\/div>\s*$/', '', $reviews_content);
            
            echo '<div id="cver-reviews-ajax-area">';
            echo $reviews_content; // Reviews inside AJAX area (without close divs)
            echo '</div>';
            
            // Close wrapper divs outside AJAX area
            echo '</div>'; // Close #comments
            echo '</div>'; // Close #reviews
        } else {
            // Fallback for AJAX calls (when skip_controls is true)
            echo '<div id="cver-reviews-ajax-area">';
            echo $initial_html;
            echo '</div>';
        }
        echo '</div>';
        return ob_get_clean();
    }
    
    // --- AJAX handlers ---
    public function handle_filter_reviews() {
        $product_id = intval($_POST['product_id'] ?? 0);
        $page = intval($_POST['page'] ?? 1);
        $per_page = intval($_POST['per_page'] ?? 4);
        $star = isset($_POST['star']) && $_POST['star'] !== '' ? intval($_POST['star']) : null;
        $sort = $_POST['sort'] ?? 'newest';
        
        // Debug log
        error_log('CVER Filter Reviews - Star: ' . var_export($star, true) . ', Sort: ' . $sort);
        
        $html = $this->get_reviews_with_pagination([
            'product_id'=>$product_id,'per_page'=>$per_page,'page'=>$page,'star'=>$star,'sort'=>$sort,'skip_controls'=>true
        ]);
        wp_send_json_success(['html'=>$html,'page'=>$page]);
    }
    // Helpful voting AJAX logic (user and guest, toggle/cancel)
    public function handle_helpful_vote() {
        $comment_id = intval($_POST['comment_id'] ?? 0);
        $value = $_POST['value'] ?? '';
        $nonce = $_POST['nonce'] ?? '';
        if (!$comment_id || !in_array($value, ['up','down']) || !wp_verify_nonce($nonce, 'cver-nonce')) {
            wp_send_json_error(['msg' => 'Invalid request']);
        }
        // Identify user (by user ID if logged in, else IP/session hash)
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $user_key = 'user-' . $user_id;
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $session = $_COOKIE[LOGGED_IN_COOKIE] ?? session_id();
            $user_key = 'ip-' . md5($ip.$session);
        }
        // Store previous
        $user_list = get_comment_meta($comment_id, 'cver_helpful_voters', true);
        $user_list = is_array($user_list) ? $user_list : [];
        $prev = $user_list[$user_key] ?? null;
        // Toggling logic
        $up = (int)get_comment_meta($comment_id,'cver_helpful_up',true);
        $down = (int)get_comment_meta($comment_id,'cver_helpful_down',true);
        if ($prev === $value) {
            // Remove vote
            unset($user_list[$user_key]);
            if ($value=='up' && $up>0) $up--;
            if ($value=='down' && $down>0) $down--;
        } else {
            // Swap vote or add new
            if     ($value=='up')   { if($down>0 && $prev=='down') $down--; $up++;   }
            elseif ($value=='down') { if($up>0 && $prev=='up')   $up--;   $down++; }
            $user_list[$user_key] = $value;
        }
        // Persist meta
        update_comment_meta($comment_id,'cver_helpful_up',$up);
        update_comment_meta($comment_id,'cver_helpful_down',$down);
        update_comment_meta($comment_id,'cver_helpful_score',$up-$down);
        update_comment_meta($comment_id,'cver_helpful_voters',$user_list);
        wp_send_json_success(['up'=>$up,'down'=>$down]);
    }
}
