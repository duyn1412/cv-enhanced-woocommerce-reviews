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
        $query = [
            'post_id' => $product_id,
            'status' => 'approve',
            'type' => 'review',
            'number' => $comments_per_page,
            'offset' => ($current_page - 1) * $comments_per_page
        ];
        // Filter by star
        if ($star) $query['meta_query'] = [
            ['key' => 'rating', 'value' => $star, 'compare' => '=']
        ];
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
        echo '<div id="reviews" class="woocommerce-Reviews">';
        echo '<div id="comments">';
        echo '<h2 class="woocommerce-Reviews-title">' . esc_html($total_comments) . ' reviews for <span>' . esc_html(get_the_title($product_id)) . '</span></h2>';
        
        // Filter & Sort UI at the top
        echo '<div class="cver-controls-wrapper">';
        echo '<div class="cver-filter-wrapper"><label for="cver-filter-dropdown">Filter by rating: </label><select id="cver-filter-dropdown">';
        echo '<option value="">All Stars</option>';
        echo '<option value="5"'.($star=='5'?' selected':'').'>5 Stars</option>';
        echo '<option value="4"'.($star=='4'?' selected':'').'>4 Stars</option>';
        echo '<option value="3"'.($star=='3'?' selected':'').'>3 Stars</option>';
        echo '<option value="2"'.($star=='2'?' selected':'').'>2 Stars</option>';
        echo '<option value="1"'.($star=='1'?' selected':'').'>1 Star</option>';
        echo '</select></div>';
        echo '<div class="cver-sorting-wrapper"><label for="cver-sort-dropdown">Sort by: </label><select id="cver-sort-dropdown">';
        echo '<option value="newest"'.($sort=='newest'?' selected':'').'>Newest</option>';
        echo '<option value="highest"'.($sort=='highest'?' selected':'').'>Highest Rating</option>';
        echo '<option value="lowest"'.($sort=='lowest'?' selected':'').'>Lowest Rating</option>';
        echo '<option value="helpful"'.($sort=='helpful'?' selected':'').'>Most Helpful</option>';
        echo '</select></div>';
        echo '</div>';
        
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
        echo '</div>';
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
        // Helpful voting area below (could also be next to each review later)
        echo '</div>';
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
        ]);
        wp_send_json_success(['html' => $html, 'page' => $page]);
    }

    // AJAX for REST API
    public function rest_load_reviews($request) {
        $args = [
            'product_id' => $request->get_param('product_id'),
            'page' => $request->get_param('page'),
            'per_page' => $request->get_param('per_page'),
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
        do_action('cver_render_summary', $product_id);
        do_action('cver_render_distribution', $product_id);
        // Render reviews below the chart
        echo $this->get_reviews_with_pagination([
            'product_id' => $product_id,
            'per_page' => 4,
            'page' => 1,
        ]);
        echo '</div>';
        return ob_get_clean();
    }
    
    // --- skeletons for sort/filter/voting (AJAX pipeline to be implemented next) ---
    public function handle_filter_reviews() {
        $product_id = intval($_POST['product_id'] ?? 0);
        $page = intval($_POST['page'] ?? 1);
        $per_page = intval($_POST['per_page'] ?? 4);
        $star = $_POST['star'] ?? null;
        $sort = $_POST['sort'] ?? 'newest';
        $html = $this->get_reviews_with_pagination([
            'product_id'=>$product_id,'per_page'=>$per_page,'page'=>$page,'star'=>$star,'sort'=>$sort
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
