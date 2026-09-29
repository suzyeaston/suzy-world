<?php
declare(strict_types=1);

namespace SuzyWorld;

final class Admin {
    public static function bootstrap(): void {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_post_suzy_world_import', [self::class, 'handle_import']);
    }

    public static function menu(): void {
        add_management_page(
            'SUZY//WORLD',
            'SUZY//WORLD',
            'manage_options',
            'suzy-world',
            [self::class, 'render']
        );
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $counts = Repository::counts();
        $message = isset($_GET['sw_message'])
            ? sanitize_text_field(wp_unslash((string) $_GET['sw_message']))
            : '';

        $api = esc_url(rest_url('suzy-world/v1'));
        ?>
        <div class="wrap">
            <h1>SUZY//WORLD</h1>
            <p><strong>Public knowledge. Explicit publication.</strong> Your private SUZY//AI world is a separate system.</p>

            <?php if ($message !== '') : ?>
                <div class="notice notice-success"><p><?php echo esc_html($message); ?></p></div>
            <?php endif; ?>

            <div style="display:flex;gap:24px;flex-wrap:wrap;margin:24px 0">
                <?php foreach ($counts as $label => $count) : ?>
                    <div style="background:#fff;border:1px solid #dcdcde;padding:16px 20px;min-width:120px">
                        <strong style="font-size:24px"><?php echo esc_html((string) $count); ?></strong><br>
                        <span><?php echo esc_html($label); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <hr>

            <h2>Import album knowledge</h2>
            <p>Upload CSV or JSON. Imports are <strong>draft by default</strong>. Exact duplicate reviews are ignored; changed reviews become new versions.</p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="suzy_world_import">
                <?php wp_nonce_field('suzy_world_import'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="sw_import_file">CSV / JSON</label></th>
                        <td><input type="file" id="sw_import_file" name="sw_import_file" accept=".csv,.json" required></td>
                    </tr>
                    <tr>
                        <th scope="row">Publish immediately</th>
                        <td>
                            <label>
                                <input type="checkbox" name="publish" value="1">
                                Make imported entities/reviews public through the REST API now.
                            </label>
                            <p class="description">Leave unchecked when experimenting.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Import albums'); ?>
            </form>

            <h3>Album import fields</h3>
            <code>artist, album, year, review, rating, tags, sonic_notes, favorite_tracks, associations, lesson, cover_url, cover_credit, cover_creator, cover_source_url, cover_rights, cover_rights_note, cover_display</code>

            <p>Tags use semicolons. Optional category syntax:</p>
            <pre>production:dry drums; composition:pedal tone; personal:changed my playing</pre>

            <p><strong>Album covers:</strong> importing a URL does not automatically authorize display. <code>cover_display</code> must be true before the shortcode will render it, and rights/credit metadata remains attached to the asset.</p>

            <hr>

            <h2>API</h2>
            <p>Public reads:</p>
            <ul>
                <li><a href="<?php echo esc_url($api . '/schema'); ?>" target="_blank" rel="noopener"><?php echo esc_html($api . '/schema'); ?></a></li>
                <li><a href="<?php echo esc_url($api . '/albums'); ?>" target="_blank" rel="noopener"><?php echo esc_html($api . '/albums'); ?></a></li>
                <li><a href="<?php echo esc_url($api . '/entities'); ?>" target="_blank" rel="noopener"><?php echo esc_html($api . '/entities'); ?></a></li>
                <li><a href="<?php echo esc_url($api . '/tags'); ?>" target="_blank" rel="noopener"><?php echo esc_html($api . '/tags'); ?></a></li>
            </ul>

            <p>External writes use normal WordPress authentication. For scripts or SUZY//AI later, create a WordPress <strong>Application Password</strong> for your account instead of inventing another password/token system.</p>

            <h2>Public album page</h2>
            <p>Create a WordPress page and place:</p>
            <pre>[suzy_world_albums]</pre>
        </div>
        <?php
    }

    public static function handle_import(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Not allowed.');
        }

        check_admin_referer('suzy_world_import');

        if (empty($_FILES['sw_import_file']['tmp_name'])) {
            self::redirect('No file uploaded.');
        }

        $file = $_FILES['sw_import_file'];
        $filename = sanitize_file_name((string) ($file['name'] ?? ''));
        $tmp = (string) ($file['tmp_name'] ?? '');
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

        if (!in_array($extension, ['csv', 'json'], true)) {
            self::redirect('Only CSV and JSON are accepted.');
        }

        if (!is_uploaded_file($tmp)) {
            self::redirect('Upload validation failed.');
        }

        try {
            $result = Importer::import_file($tmp, $filename, !empty($_POST['publish']));
            self::redirect(
                sprintf(
                    'Import complete: %d inserted, %d duplicates, %d errors.',
                    (int) $result['inserted'],
                    (int) $result['duplicates'],
                    (int) $result['errors']
                )
            );
        } catch (\Throwable $e) {
            self::redirect('Import failed: ' . $e->getMessage());
        }
    }

    private static function redirect(string $message): void {
        $url = add_query_arg(
            [
                'page' => 'suzy-world',
                'sw_message' => $message,
            ],
            admin_url('tools.php')
        );
        wp_safe_redirect($url);
        exit;
    }
}
