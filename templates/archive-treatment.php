<?php
/**
 * Archive Medical Treatments Template - CareToChina Medical Suite
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$tax_title = __('Explore Advanced Medical Treatments', 'caretochina-medical');
$tax_desc  = __('Discover world-class medical procedures, innovative treatments, and specialized care in China.', 'caretochina-medical');

if (is_tax('treatment_category')) {
    $current_term = get_queried_object();
    if ($current_term) {
        $tax_title = $current_term->name;
        if (!empty($current_term->description)) {
            $tax_desc = $current_term->description;
        }
    }
}

// Fetch all categories for filter tabs
$categories = get_terms([
    'taxonomy'   => 'treatment_category',
    'hide_empty' => false,
]);

$current_cat_slug = is_tax('treatment_category') && isset($current_term->slug) ? $current_term->slug : 'all';
?>

<div class="ctc-treatments-archive-page">
    
    <!-- Archive Hero Header -->
    <header class="ctc-archive-hero">
        <div class="ctc-archive-container">
            <div class="ctc-archive-badge"><i class="fas fa-heartbeat"></i> <?php esc_html_e('Medical Procedures & Treatments', 'caretochina-medical'); ?></div>
            <h1 class="ctc-archive-title"><?php echo esc_html($tax_title); ?></h1>
            <p class="ctc-archive-desc"><?php echo esc_html($tax_desc); ?></p>
        </div>
    </header>

    <!-- Main Directory Section -->
    <main class="ctc-archive-container ctc-archive-body">
        
        <div class="ctc-treatments-section" id="ctc-archive-treatments-root">
            
            <!-- Filter & Search Bar -->
            <div class="ctc-treat-filter-bar">
                <div class="ctc-treat-search-box">
                    <i class="fas fa-search ctc-treat-search-icon"></i>
                    <input type="text" class="ctc-treat-search-input" placeholder="<?php esc_attr_e('Search treatments by procedure name or keyword...', 'caretochina-medical'); ?>" aria-label="<?php esc_attr_e('Search medical treatments', 'caretochina-medical'); ?>">
                </div>

                <?php if (!empty($categories) && !is_wp_error($categories)) : ?>
                    <div class="ctc-treat-cat-tabs">
                        <button type="button" class="ctc-treat-cat-tab <?php echo ($current_cat_slug === 'all') ? 'active' : ''; ?>" data-category="all">
                            <i class="fas fa-th-large"></i> <?php esc_html_e('All Treatments', 'caretochina-medical'); ?>
                        </button>
                        <?php foreach ($categories as $cat) : ?>
                            <button type="button" class="ctc-treat-cat-tab <?php echo ($current_cat_slug === $cat->slug) ? 'active' : ''; ?>" data-category="<?php echo esc_attr($cat->slug); ?>">
                                <i class="fas fa-stethoscope"></i> <?php echo esc_html($cat->name); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Treatment Grid -->
            <div class="ctc-treatments-grid">
                <?php if (have_posts()) : ?>
                    <?php while (have_posts()) : the_post(); ?>
                        <?php CareToChina_Treatments_Plugin::render_treatment_card(get_the_ID()); ?>
                    <?php endwhile; ?>
                <?php else : ?>
                    <div class="ctc-no-treatments" style="grid-column: 1 / -1; text-align: center; padding: 60px 20px; color: #64748b; width: 100%;">
                        <i class="fas fa-heartbeat" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 12px; display: block;"></i>
                        <span style="font-size: 1.1rem;"><?php esc_html_e('No medical treatments found matching your criteria.', 'caretochina-medical'); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <div class="ctc-treat-pagination-box" id="ctc-archive-pagination">
                <?php
                global $wp_query;
                if ($wp_query->max_num_pages > 1) :
                    for ($i = 1; $i <= $wp_query->max_num_pages; $i++) {
                        $active_cls = ($i === (get_query_var('paged') ? get_query_var('paged') : 1)) ? 'active' : '';
                        echo '<button type="button" class="ctc-treat-page-btn ' . esc_attr($active_cls) . '" data-page="' . esc_attr($i) . '">' . esc_html($i) . '</button>';
                    }
                endif;
                ?>
            </div>

        </div>

    </main>

</div>

<style>
    .ctc-treatments-archive-page {
        width: 100%;
        background-color: #f8fafc;
        min-height: 100vh;
        padding-bottom: 90px;
    }
    .ctc-archive-container {
        max-width: 1240px;
        margin: 0 auto;
        padding: 0 24px;
        box-sizing: border-box;
    }
    .ctc-archive-hero {
        background: linear-gradient(135deg, #0f172a 0%, #134e4a 100%);
        padding: 60px 0 70px 0;
        color: #ffffff;
        text-align: center;
        margin-bottom: 44px;
    }
    .ctc-archive-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(45, 212, 191, 0.18);
        border: 1px solid rgba(45, 212, 191, 0.35);
        color: #2dd4bf;
        padding: 6px 16px;
        border-radius: 40px;
        font-size: 0.82rem;
        font-weight: 600;
        margin-bottom: 16px;
    }
    .ctc-archive-title {
        font-family: 'Manrope', sans-serif;
        font-size: 2.6rem;
        font-weight: 800;
        color: #ffffff;
        margin: 0 0 14px 0;
        line-height: 1.25;
    }
    .ctc-archive-desc {
        font-family: 'Inter', sans-serif;
        font-size: 1.1rem;
        color: #cbd5e1;
        max-width: 700px;
        margin: 0 auto;
        line-height: 1.6;
    }

    .ctc-treat-filter-bar {
        display: flex;
        flex-direction: column;
        gap: 20px;
        margin-bottom: 36px;
        align-items: center;
        justify-content: center;
        text-align: center;
    }
    .ctc-treat-search-box {
        position: relative !important;
        width: 100% !important;
        max-width: 620px !important;
        margin: 0 auto !important;
    }
    .ctc-treat-search-icon {
        position: absolute !important;
        left: 18px !important;
        top: 50% !important;
        transform: translateY(-50%) !important;
        color: #0f766e !important;
        font-size: 1rem !important;
        z-index: 5 !important;
        pointer-events: none !important;
    }
    .ctc-treat-search-input {
        width: 100% !important;
        padding: 14px 20px 14px 48px !important;
        border-radius: 999px !important;
        border: 1.5px solid #cbd5e1 !important;
        background: #ffffff !important;
        color: #0f172a !important;
        font-family: 'Inter', sans-serif !important;
        font-size: 0.95rem !important;
        outline: none !important;
        transition: all 0.3s ease !important;
        box-shadow: 0 4px 12px rgba(15, 118, 110, 0.05) !important;
        box-sizing: border-box !important;
    }
    .ctc-treat-search-input:focus {
        border-color: #0f766e !important;
        box-shadow: 0 6px 16px rgba(15, 118, 110, 0.15) !important;
    }

    .ctc-treat-cat-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: center;
        width: 100%;
        margin: 0 auto;
    }
    .ctc-treat-cat-tab {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: 999px;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        color: #475569;
        font-family: 'Manrope', sans-serif;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .ctc-treat-cat-tab:hover {
        background: #ccfbf1;
        color: #0f766e;
        border-color: #0f766e;
    }
    .ctc-treat-cat-tab.active {
        background: #0f766e;
        color: #ffffff;
        border-color: #0f766e;
        box-shadow: 0 4px 12px rgba(15, 118, 110, 0.25);
    }

    .ctc-treatments-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        transition: opacity 0.25s ease;
    }

    .ctc-treat-pagination-box {
        display: flex;
        justify-content: center;
        gap: 8px;
        margin-top: 40px;
        width: 100%;
        flex-wrap: wrap;
    }
    .ctc-treat-page-btn {
        min-width: 42px;
        height: 42px;
        padding: 0 12px;
        border-radius: 12px;
        background: #ffffff;
        border: 1.5px solid #e2e8f0;
        color: #0f172a;
        font-family: 'Manrope', sans-serif;
        font-weight: 700;
        font-size: 0.95rem;
        cursor: pointer;
        transition: all 0.25s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .ctc-treat-page-btn:hover {
        border-color: #0f766e;
        color: #0f766e;
        background: #ccfbf1;
    }
    .ctc-treat-page-btn.active {
        background: #0f766e;
        color: #ffffff;
        border-color: #0f766e;
        box-shadow: 0 4px 12px rgba(15, 118, 110, 0.2);
    }

    @media (max-width: 1200px) {
        .ctc-treatments-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    @media (max-width: 900px) {
        .ctc-treatments-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    @media (max-width: 600px) {
        .ctc-treatments-grid {
            grid-template-columns: 1fr;
        }
        .ctc-archive-title {
            font-size: 1.9rem;
        }
    }

    /* Dark Mode Mode Integration */
    html.dark-theme .ctc-treatments-archive-page, body.dark-theme .ctc-treatments-archive-page {
        background-color: #0a0e1a !important;
    }
    html.dark-theme .ctc-treat-search-input, body.dark-theme .ctc-treat-search-input {
        background-color: #172033 !important;
        border-color: #28354e !important;
        color: #f8fafc !important;
    }
    html.dark-theme .ctc-treat-search-icon, body.dark-theme .ctc-treat-search-icon {
        color: #14b8a6 !important;
    }
    html.dark-theme .ctc-treat-cat-tab, body.dark-theme .ctc-treat-cat-tab {
        background-color: #172033 !important;
        border-color: #28354e !important;
        color: #94a3b8 !important;
    }
    html.dark-theme .ctc-treat-cat-tab.active, body.dark-theme .ctc-treat-cat-tab.active {
        background-color: #0f766e !important;
        color: #ffffff !important;
    }
    html.dark-theme .ctc-treat-page-btn, body.dark-theme .ctc-treat-page-btn {
        background-color: #172033 !important;
        border-color: #28354e !important;
        color: #f8fafc !important;
    }
    html.dark-theme .ctc-treat-page-btn.active, body.dark-theme .ctc-treat-page-btn.active {
        background-color: #0f766e !important;
        color: #ffffff !important;
        border-color: #0f766e !important;
    }
</style>

<script>
(function() {
    function initArchiveTreatments() {
        if (typeof jQuery === 'undefined') {
            setTimeout(initArchiveTreatments, 50);
            return;
        }
        var $ = jQuery;
        var $section = $('#ctc-archive-treatments-root');
        if (!$section.length || $section.data('archive-init') === true) return;
        $section.data('archive-init', true);

        var $grid = $section.find('.ctc-treatments-grid');
        var $pagBox = $('#ctc-archive-pagination');
        var currentCategory = '<?php echo esc_js($current_cat_slug); ?>';
        var currentPage = 1;
        var searchTimeout;

        function doFilter(page) {
            if (!page) page = 1;
            currentPage = page;
            var searchVal = $section.find('.ctc-treat-search-input').val();

            $grid.css('opacity', '0.5');

            $.ajax({
                url: '<?php echo esc_url_raw(admin_url('admin-ajax.php')); ?>',
                type: 'POST',
                data: {
                    action: 'caretochina_filter_treatments',
                    category: currentCategory,
                    search: searchVal,
                    page: currentPage,
                    posts_per_page: 6
                },
                success: function(res) {
                    $grid.css('opacity', '1');
                    if (res.success) {
                        $grid.html(res.data.html);
                        $pagBox.html(res.data.pagination_html);
                    }
                },
                error: function() {
                    $grid.css('opacity', '1');
                }
            });
        }

        $section.on('click', '.ctc-treat-cat-tab', function() {
            $section.find('.ctc-treat-cat-tab').removeClass('active');
            $(this).addClass('active');
            currentCategory = $(this).data('category');
            doFilter(1);
        });

        $section.on('keyup input', '.ctc-treat-search-input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                doFilter(1);
            }, 300);
        });

        $section.on('click', '.ctc-treat-page-btn', function() {
            var p = $(this).data('page');
            doFilter(p);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initArchiveTreatments);
    } else {
        initArchiveTreatments();
    }
})();
</script>

<?php
get_footer();
