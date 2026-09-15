<?php
/**
 * Veloura Tec — Admin action: product operations.
 *
 * POST only, CSRF-checked, admin-only.
 */

require dirname(__DIR__) . '/_auth.php';

if (!is_post()) {
    redirect('admin/products.php');
}

csrf_guard();

$op       = (string) input('op', '');
$id       = input_int('id');
$returnTo = admin_safe_return(input('return_to'), 'admin/products.php');

// Every operation here needs a real product.
if ($id <= 0 || product_by_id($id) === null) {
    flash('error', 'That product no longer exists.');
    redirect($returnTo);
}

switch ($op) {

    case 'delete':
        product_delete($id);
        flash('success', 'Product deleted.');

        // Never bounce back to the edit screen of a deleted product.
        if (str_contains($returnTo, 'product-edit.php')) {
            $returnTo = 'admin/products.php';
        }
        break;

    case 'toggle-status':
        $status = product_toggle_status($id);
        flash('success', $status === 'published' ? 'Product published.' : 'Product moved to draft.');
        break;

    case 'toggle-featured':
        $featured = product_toggle_featured($id);
        flash('success', $featured ? 'Product marked as featured.' : 'Product removed from featured.');
        break;

    case 'image-delete':
        if (product_image_delete(input_int('image_id'), $id)) {
            flash('success', 'Image deleted.');
        } else {
            flash('error', 'That image was not found on this product.');
        }
        break;

    case 'image-make-main':
        if (product_image_make_main(input_int('image_id'), $id)) {
            flash('success', 'Main image updated.');
        } else {
            flash('error', 'That image was not found on this product.');
        }
        break;

    case 'video-add':
        $type  = (string) input('video_type', 'youtube');
        $title = (string) input('video_title', '');

        if ($type === 'file') {
            if (!upload_present('video_file')) {
                flash('error', 'Choose a video file to upload.');
                break;
            }

            try {
                $path   = upload_video('video_file', 'products');
                $reason = product_video_add($id, 'file', $path, $title);

                if ($reason !== null) {
                    upload_delete($path);
                    flash('error', $reason);
                } else {
                    flash('success', 'Video uploaded.');
                }
            } catch (UploadException $e) {
                flash('error', $e->getMessage());
            }
            break;
        }

        $reason = product_video_add($id, $type, (string) input('video_url', ''), $title);

        if ($reason !== null) {
            flash('error', $reason);
        } else {
            flash('success', 'Video added.');
        }
        break;

    case 'video-delete':
        if (product_video_delete(input_int('video_id'), $id)) {
            flash('success', 'Video deleted.');
        } else {
            flash('error', 'That video was not found on this product.');
        }
        break;

    default:
        flash('error', 'Unknown action.');
}

redirect($returnTo);
