<?php
declare(strict_types=1);

namespace SuzyWorld;

final class Shortcode {
    public static function bootstrap(): void {
        add_shortcode('suzy_world_albums', [self::class, 'albums']);
    }

    public static function albums(array $atts = []): string {
        $atts = shortcode_atts(['limit' => 24], $atts, 'suzy_world_albums');
        $limit = max(1, min(100, (int) $atts['limit']));
        $albums = Repository::albums($limit);

        wp_enqueue_style(
            'suzy-world',
            SUZY_WORLD_URL . 'public/suzy-world.css',
            [],
            SUZY_WORLD_VERSION . '-' . (string) @filemtime(SUZY_WORLD_PATH . 'public/suzy-world.css')
        );

        ob_start();
        ?>
        <section class="suzy-world-albums">
            <header class="sw-head">
                <p>SUZY//WORLD · MUSIC</p>
                <h2>Records in the world</h2>
            </header>

            <?php if (!$albums) : ?>
                <p class="sw-empty">No published album reviews yet.</p>
            <?php else : ?>
                <div class="sw-grid">
                    <?php foreach ($albums as $album) : ?>
                        <?php
                        $cover = $album['assets'][0] ?? null;
                        $review = $album['latest_review'] ?? null;
                        $tags = $album['tags'] ?? [];
                        ?>
                        <article class="sw-card">
                            <?php if (is_array($cover) && !empty($cover['url'])) : ?>
                                <img
                                    class="sw-cover"
                                    src="<?php echo esc_url((string) $cover['url']); ?>"
                                    alt="<?php echo esc_attr((string) ($cover['alt_text'] ?? '')); ?>"
                                    loading="lazy"
                                >
                            <?php endif; ?>

                            <div class="sw-card-body">
                                <p class="sw-artist"><?php echo esc_html((string) $album['artist']); ?></p>
                                <h3><?php echo esc_html((string) $album['album']); ?></h3>

                                <?php if (!empty($album['year'])) : ?>
                                    <p class="sw-year"><?php echo esc_html((string) $album['year']); ?></p>
                                <?php endif; ?>

                                <?php if (is_array($review) && !empty($review['body'])) : ?>
                                    <div class="sw-review">
                                        <?php echo wp_kses_post(wpautop(wp_trim_words((string) $review['body'], 70))); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($tags) : ?>
                                    <div class="sw-tags">
                                        <?php foreach ($tags as $tag) : ?>
                                            <span><?php echo esc_html((string) $tag['name']); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (is_array($cover) && !empty($cover['credit'])) : ?>
                                    <p class="sw-credit">Cover: <?php echo esc_html((string) $cover['credit']); ?></p>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }
}
