<?php
/**
 * Plugin Name: Employee ID Card Generator
 * Description: Generate employee ID cards with a front and back side, PDF download, and QR code.
 * Version: 4.0.6
 * Author: Abhijith P S
 */

if (!defined('ABSPATH')) exit;

// Include Libraries
require_once plugin_dir_path(__FILE__) . 'vendor/phpqrcode/qrlib.php';
require_once plugin_dir_path(__FILE__) . 'vendor/tcpdf/tcpdf.php';

// Register Custom Post Type
function register_id_card_post_type() {
    register_post_type('id_card', [
        'labels' => [
            'name' => __('ID Cards', 'id-card-plugin'),
            'singular_name' => __('ID Card', 'id-card-plugin')
        ],
        'public' => true,
        'has_archive' => true,
        'rewrite' => ['slug' => 'id_card'],
        'supports' => ['title', 'editor', 'thumbnail'],
        'menu_icon' => 'dashicons-id',
        'capability_type' => 'post'
    ]);
}
add_action('init', 'register_id_card_post_type');


// Create Employee Data Table on Plugin Activation
function id_card_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'employee_id_cards';

    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        designation VARCHAR(255),
        emp_code VARCHAR(50) UNIQUE NOT NULL,
        blood_group VARCHAR(10),
        address1 TEXT,
        address2 TEXT,
        photo_url TEXT,
        qr_code_url TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'id_card_create_table');


// Flush rewrite rules on activation/deactivation
function id_card_plugin_activate() {
    register_id_card_post_type();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'id_card_plugin_activate');

function id_card_plugin_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'id_card_plugin_deactivate');

// Insert Employee Data into Custom Table
function insert_employee_data($name, $designation, $emp_code, $blood_group, $address1, $address2, $photo_url, $qr_code_url) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'employee_id_cards';

    $wpdb->insert($table_name, [
        'name' => $name,
        'designation' => $designation,
        'emp_code' => $emp_code,
        'blood_group' => $blood_group,
        'address1' => $address1,
        'address2' => $address2,
        'photo_url' => $photo_url,
        'qr_code_url' => $qr_code_url
    ]);
}

// Insert Employee Data into Custom Table
function create_or_update_id_card() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'employee_id_cards';

    if (!isset($_POST['generate_id_card_nonce']) || !wp_verify_nonce($_POST['generate_id_card_nonce'], 'generate_id_card_action')) {
        wp_die('Security check failed');
        exit();
    }

    $name = sanitize_text_field($_POST['name']);
    $designation = sanitize_text_field($_POST['designation']);
    $emp_code = sanitize_text_field($_POST['emp_code']);
    $blood_group = sanitize_text_field($_POST['blood_group']);
    $address1 = sanitize_textarea_field($_POST['address1']);
    $address2 = sanitize_textarea_field($_POST['address2']);
    $photo_url = upload_file('employee_photo');
    $post_id = wp_insert_post([
        'post_title' => $name . ' - ' . $emp_code,
        'post_status' => 'publish',
        'post_type' => 'id_card'
    ]);

    if (is_wp_error($post_id)) {
        wp_die(__('Error creating ID card: ', 'id-card-plugin') . $post_id->get_error_message(), 400);
    }

    $qr_code_url = generate_qr_code(get_permalink($post_id), $emp_code);
    //$qr_code_url = generate_qr_code(get_permalink(), $emp_code);

    // Check if Employee Already Exists
    $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE emp_code = %s", $emp_code));

    if ($existing) {
        // Update existing record
        $sql = $wpdb->prepare(
            "UPDATE $table_name SET name=%s, designation=%s, blood_group=%s, address1=%s, address2=%s, photo_url=%s, qr_code_url=%s WHERE emp_code=%s",
            $name, $designation, $blood_group, $address1, $address2, $photo_url, $qr_code_url, $emp_code
        );
        error_log("Generated SQL: " . $sql);
        $wpdb->query($sql);
    } else {
        // Insert new record
        $wpdb->insert($table_name, compact('name', 'designation', 'emp_code', 'blood_group', 'address1', 'address2', 'photo_url', 'qr_code_url'));
    }
    update_post_meta($post_id, 'emp_code', $emp_code);
    //$qr_code_url = generate_qr_code(get_permalink($post_id), $emp_code);

    wp_update_post(['ID' => $post_id, 'post_content' => generate_id_card_html($name, $designation, $emp_code, $blood_group, $address1, $address2, $photo_url, $qr_code_url, $post_id)]);
    wp_redirect(get_permalink($post_id));
    exit();
}
add_action('admin_post_generate_id_card', 'create_or_update_id_card');

// Handle PDF Download Request
add_action('init', function() {
    if (isset($_GET['download_id_card']) && isset($_GET['post_id'])) {
        $post_id = intval($_GET['post_id']);
        $emp_code = get_post_meta($post_id, 'emp_code', true);
        if ($emp_code) {
            generate_pdf_id_card($emp_code);
        } else {
            wp_redirect(home_url());
            exit();
        }
    }
});
// Upload Employee Photo
function upload_file($file_input) {
    if (!function_exists('wp_handle_upload')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    if (!empty($_FILES[$file_input]['name'])) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES[$file_input]['type'];
        if (!in_array($file_type, $allowed_types)) {
            wp_die(__('Invalid file type. Only JPG, PNG, and GIF files are allowed.', 'id-card-plugin'));
        }

        $uploaded_file = wp_handle_upload($_FILES[$file_input], ['test_form' => false]);
        return isset($uploaded_file['url']) ? esc_url($uploaded_file['url']) : false;
    }
    return plugin_dir_url(__FILE__) . 'assets/photo-icon.png';
}

// Generate QR Code
function generate_qr_code($url, $emp_code) {
    $qr_dir = plugin_dir_path(__FILE__) . 'qr_codes/';
    if (!file_exists($qr_dir)) {
        mkdir($qr_dir, 0755, true);
    }
    $qr_file = $qr_dir . $emp_code . '.png';

    if (function_exists('imagecreate')) {
        QRcode::png($url, $qr_file, QR_ECLEVEL_L, 5);
        return plugin_dir_url(__FILE__) . 'qr_codes/' . $emp_code . '.png';
    }
    return 'https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=' . urlencode($url) . '&choe=UTF-8';
}
// Generate PDF with Data from Custom Table
function generate_pdf_id_card($emp_code) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'employee_id_cards';

    // Fetch Employee Data
    $employee = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE emp_code = %s", $emp_code));

    if (!$employee) {
        wp_die('Employee not found.');
    }

    // Initialize TCPDF
    $pdf = new TCPDF();
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetTitle('Employee ID Card - ' . $employee->name);
    $pdf->SetMargins(10, 10, 10);
    $pdf->AddPage();

    // Set Styles
    $pdf->SetFont('helvetica', '', 12);

    // ✅ FRONT SIDE - Employee Name & QR Code
    $pdf->SetFillColor(255, 255, 255); // White Background
    $pdf->Rect(10, 10, 90, 130, 'D'); // Border around ID card

    $pdf->Image(plugin_dir_path(__FILE__) . 'assets/company-logo.png', 35, 15, 42, 13, 'PNG', '', '', true);


       
            $file_info = wp_check_filetype($employee->photo_url);
            $pdf->Image(str_replace(site_url(), ABSPATH, $employee->photo_url), 15, 40, 80, 40, $file_info['ext'] == 'jpeg' ? 'JPG' : 'PNG', '', '', true);
       
    

    $pdf->SetXY(15, 88);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(80, 10, $employee->name, 0, 1, 'C');

    if (!empty($employee->qr_code_url) && file_exists(str_replace(site_url(), ABSPATH, $employee->qr_code_url))) {
        $pdf->Image(str_replace(site_url(), ABSPATH, $employee->qr_code_url), 33, 98, 40, 40, 'PNG', '', '', true);
    }

    // ✅ BACK SIDE - Employee Code, Blood Group, Address
    $pdf->SetXY(110, 10);
    $pdf->Rect(110, 10, 90, 130, 'D');

    $pdf->SetXY(115, 20);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(80, 10, 'Employee Code: ' . $employee->emp_code, 0, 1, 'R');

    $pdf->SetXY(115, 30);
    $pdf->Cell(80, 10, 'Blood Group: ' . $employee->blood_group, 0, 1, 'R');

    $pdf->Image(plugin_dir_path(__FILE__) . 'assets/photo-icon.png', 140, 50, 30, 30, 'PNG', '', '', true);
    $pdf->Image(plugin_dir_path(__FILE__) . 'assets/company-logo.png', 133, 90, 42, 13, 'PNG', '', '', true);

    // Ensure Address is Visible & Aligned
    $pdf->SetXY(115, 110);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->MultiCell(80, 10, trim($employee->address1 . "\n" . $employee->address2), 0, 'C');

    // Output PDF
    $pdf->Output('ID_Card_' . sanitize_title($employee->name) . '.pdf', 'D');
    exit;
}



// Enqueue Styles
function id_card_enqueue_styles() {
    wp_enqueue_style('id-card-styles', plugin_dir_url(__FILE__) . 'style.css');
}
add_action('admin_enqueue_scripts', 'id_card_enqueue_styles');

// Handle Form Submission
/*add_action('admin_post_generate_id_card', 'create_or_update_id_card');
function create_or_update_id_card() {
    if (!isset($_POST['generate_id_card_nonce']) || !wp_verify_nonce($_POST['generate_id_card_nonce'], 'generate_id_card_action')) {
        wp_die(__('Security check failed.', 'id-card-plugin'), 403);
    }

    if (!current_user_can('edit_posts')) {
        wp_die(__('Insufficient permissions.', 'id-card-plugin'), 403);
    }

    $required_fields = ['name', 'designation', 'emp_code', 'blood_group', 'address1'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            wp_die(__('Missing required field: ', 'id-card-plugin') . ucfirst(str_replace('_', ' ', $field)), 400);
        }
    }

    $name = sanitize_text_field($_POST['name']);
    $designation = sanitize_text_field($_POST['designation']);
    $emp_code = sanitize_text_field($_POST['emp_code']);
    $blood_group = sanitize_text_field($_POST['blood_group']);
    $address1 = sanitize_textarea_field($_POST['address1']);
    $address2 = sanitize_textarea_field($_POST['address2']);
    $photo_url = upload_file('employee_photo');

    // Check if Employee Code already exists
    $existing_id_card = get_posts([
        'post_type' => 'id_card',
        'meta_query' => [['key' => 'employee_code', 'value' => $emp_code, 'compare' => '=']]
    ]);

    $post_id = $existing_id_card ? $existing_id_card[0]->ID : wp_insert_post([
        'post_title' => $name . ' - ' . $emp_code,
        'post_status' => 'publish',
        'post_type' => 'id_card'
    ]);

    if (is_wp_error($post_id)) {
        wp_die(__('Error creating ID card: ', 'id-card-plugin') . $post_id->get_error_message(), 400);
    }

    update_post_meta($post_id, 'employee_code', $emp_code);
    $qr_code_url = generate_qr_code(get_permalink($post_id), $emp_code);

    wp_update_post(['ID' => $post_id, 'post_content' => generate_id_card_html($name, $designation, $emp_code, $blood_group, $address1, $address2, $photo_url, $qr_code_url, $post_id)]);
    wp_redirect(get_permalink($post_id));
    exit();
}
*/
// Generate ID Card HTML
function generate_id_card_html($name, $designation, $emp_code, $blood_group, $address1, $address2, $photo_url, $qr_code_url, $post_id) {
    $logo_url = plugin_dir_url(__FILE__) . 'assets/company-logo.png';
    $pdf_download_url = site_url() . '?download_id_card=1&post_id=' . $post_id;
    $photo_icon = plugin_dir_url(__FILE__) . 'assets/photo-icon.png';

    return "
    <div class='id-card-container' style='display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 20px;'>
        
        <!-- Front Side -->
        <div class='id-card' style='width: 350px; height: 500px; text-align: center; padding: 20px; border-radius: 10px; background-color: white; border: 1px solid #ccc; display: flex; flex-direction: column; align-items: center; justify-content: center;'>
            <img src='$logo_url' style='width: 150px; margin-bottom: 10px;margin-top: 20px;'>
            <img src='$photo_url' style='width: 100%; height: 180px; object-fit: cover; margin-bottom: 10px; border-radius: 10px;'>         
            <h3 style='margin: 5px 0;font-weight: bold;'>$name</h3>
            <p style='margin: 0; font-weight: bold;'>$designation</p>
            <div style='width: 100%; display: flex; justify-content: center;'>
                <img src='$qr_code_url' style='width: 100px; margin-top: 10px;'>
            </div>
        </div>
        
        <!-- Back Side -->
        <div class='id-card' style='width: 350px; height: 500px; text-align: center; padding: 20px; border-radius: 10px; background-color: white; border: 1px solid #ccc; display: flex; flex-direction: column; align-items:center; justify-content: center;'>
            <div style='text-align: right; width: 100%;'>
                <p style='margin: 2px 0;font-size:14px;'><strong>Blood Group:</strong> $blood_group</p>
                <p style='margin: 2px 0; font-size:14px;'><strong>Employee Code:</strong> $emp_code</p>
            </div>
            <img src='$photo_icon' style='width: 100px; height: 100px;  display: block; margin: auto;'>
             <img src='$logo_url' style='width: 150px; margin-bottom: 10px;margin-top: 20px;'>
            <p style='margin: 2px 0;font-size:14px;'>$address1</p>
            <p style='margin: 2px 0;font-size:14px;'>$address2</p>
        </div>
    </div>
    <br>
    <a href='$pdf_download_url' class='button button-primary'>Download PDF</a>";
}

// Admin Page for ID Card Generator
function id_card_admin_page() {
    ?>
    <div class="wrap id-card-form">
        <h2><?php _e('Employee ID Card Generator', 'id-card-plugin'); ?></h2>
        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="generate_id_card">
            <?php wp_nonce_field('generate_id_card_action', 'generate_id_card_nonce'); ?>
            <label><?php _e('Name:', 'id-card-plugin'); ?></label> <input type="text" name="name" required><br>
            <label><?php _e('Designation:', 'id-card-plugin'); ?></label> <input type="text" name="designation" required><br>
            <label><?php _e('Employee Code:', 'id-card-plugin'); ?></label> <input type="text" name="emp_code" required><br>
            <label><?php _e('Blood Group:', 'id-card-plugin'); ?></label> <input type="text" name="blood_group"><br>
            <label><?php _e('Office Address:', 'id-card-plugin'); ?></label> <textarea name="address1"></textarea><br>
            <label><?php _e('Office Address 2:', 'id-card-plugin'); ?></label> <textarea name="address2"></textarea><br>
            <label><?php _e('Employee Photo:', 'id-card-plugin'); ?></label> <input type="file" name="employee_photo" accept="image/*"><br>
            <input type="submit" value="<?php _e('Generate ID Card', 'id-card-plugin'); ?>">
        </form>
    </div>
    <?php
}
add_action('admin_menu', function() {
    add_menu_page('ID Card Generator', 'ID Card Generator', 'manage_options', 'id-card-generator', 'id_card_admin_page', 'dashicons-id', 20);
});
// Enqueue Admin Styles
function id_card_admin_styles() {
    echo '
    <style>
       .id-card-form {
    max-width: 600px;
    background: #ffffff;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
}
        .wrap form label {
            display: block;
            margin-top: 10px;
            font-weight: bold;
        }
        .wrap form input[type="text"],
        .wrap form textarea,
        .wrap form input[type="file"] {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            box-sizing: border-box;
        }
        .wrap form input[type="submit"] {
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #0073aa;
            border: none;
            color: white;
            cursor: pointer;
        }
        .wrap form input[type="submit"]:hover {
            background-color:rgb(255, 255, 255);
            border: 1px solid #0073aa;
        }
    </style>
    ';
}
add_action('admin_head', 'id_card_admin_styles');