<?php
/**
 * Plugin Name: IS-Press
 * Description: Yerel WordPress sitenizde iş planlarınızı ve görev hatırlatıcılarınızı yönetin.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL-3.0-or-later
 * Text Domain: is-press
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class IS_Press {
	const POST_TYPE          = 'is_press_task';
	const META_DUE_DATE      = '_is_press_due_date';
	const META_REMINDER      = '_is_press_reminder';
	const META_REMINDER_SENT = '_is_press_reminder_sent';
	const META_STATUS        = '_is_press_status';
	const CRON_HOOK          = 'is_press_send_due_reminders';

	/**
	 * Register plugin hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_task' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'add_list_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_list_column' ), 10, 2 );
		add_action( self::CRON_HOOK, array( __CLASS__, 'send_due_reminders' ) );
		add_action( 'init', array( __CLASS__, 'schedule_reminders' ) );
	}

	/**
	 * Register the private work-plan task type.
	 */
	public static function register_post_type() {
		$labels = array(
			'name'                  => __( 'İş Planları', 'is-press' ),
			'singular_name'         => __( 'İş Planı', 'is-press' ),
			'menu_name'             => __( 'İş Planları', 'is-press' ),
			'name_admin_bar'        => __( 'İş Planı', 'is-press' ),
			'add_new'               => __( 'Yeni ekle', 'is-press' ),
			'add_new_item'          => __( 'Yeni iş planı ekle', 'is-press' ),
			'edit_item'             => __( 'İş planını düzenle', 'is-press' ),
			'new_item'              => __( 'Yeni iş planı', 'is-press' ),
			'view_item'             => __( 'İş planını görüntüle', 'is-press' ),
			'search_items'          => __( 'İş planlarında ara', 'is-press' ),
			'not_found'             => __( 'İş planı bulunamadı.', 'is-press' ),
			'not_found_in_trash'    => __( 'Çöp kutusunda iş planı bulunamadı.', 'is-press' ),
			'all_items'             => __( 'Tüm iş planları', 'is-press' ),
			'archives'              => __( 'İş planları', 'is-press' ),
			'attributes'            => __( 'İş planı özellikleri', 'is-press' ),
			'insert_into_item'      => __( 'İş planına ekle', 'is-press' ),
			'uploaded_to_this_item' => __( 'Bu iş planına yüklendi', 'is-press' ),
			'filter_items_list'     => __( 'İş planı listesini filtrele', 'is-press' ),
			'items_list_navigation' => __( 'İş planı listesi gezinme', 'is-press' ),
			'items_list'            => __( 'İş planı listesi', 'is-press' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => $labels,
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => true,
				'menu_icon'           => 'dashicons-clipboard',
				'has_archive'         => false,
				'rewrite'             => false,
				'exclude_from_search' => true,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => array( 'title', 'editor', 'author' ),
			)
		);
	}

	/**
	 * Add task fields to the native WordPress editor.
	 */
	public static function add_meta_box() {
		add_meta_box(
			'is-press-task-details',
			__( 'Görev ayrıntıları', 'is-press' ),
			array( __CLASS__, 'render_meta_box' ),
			self::POST_TYPE,
			'normal',
			'default'
		);
	}

	/**
	 * Render accessible, explicitly labelled task fields.
	 *
	 * @param WP_Post $post Current post.
	 */
	public static function render_meta_box( $post ) {
		wp_nonce_field( 'is_press_save_task', 'is_press_task_nonce' );

		$due_date = get_post_meta( $post->ID, self::META_DUE_DATE, true );
		$reminder = (int) get_post_meta( $post->ID, self::META_REMINDER, true );
		$status   = get_post_meta( $post->ID, self::META_STATUS, true );

		if ( ! in_array( $status, array( 'planned', 'in_progress', 'completed' ), true ) ) {
			$status = 'planned';
		}
		?>
		<p>
			<label for="is-press-due-date"><?php esc_html_e( 'Son tarih', 'is-press' ); ?></label><br>
			<input type="date" id="is-press-due-date" name="is_press_due_date" value="<?php echo esc_attr( $due_date ); ?>">
		</p>
		<p>
			<label for="is-press-reminder"><?php esc_html_e( 'E-posta hatırlatma zamanı', 'is-press' ); ?></label><br>
			<input type="datetime-local" id="is-press-reminder" name="is_press_reminder" aria-describedby="is-press-reminder-description" value="<?php echo esc_attr( $reminder ? wp_date( 'Y-m-d\TH:i', $reminder, wp_timezone() ) : '' ); ?>">
			<span id="is-press-reminder-description" class="description"><?php esc_html_e( 'Saat, WordPress’in yerel saat dilimine göre değerlendirilir. Hatırlatma görev yazarına e-posta ile gönderilir.', 'is-press' ); ?></span>
		</p>
		<p>
			<label for="is-press-status"><?php esc_html_e( 'Görev durumu', 'is-press' ); ?></label><br>
			<select id="is-press-status" name="is_press_status">
				<option value="planned" <?php selected( $status, 'planned' ); ?>><?php esc_html_e( 'Planlandı', 'is-press' ); ?></option>
				<option value="in_progress" <?php selected( $status, 'in_progress' ); ?>><?php esc_html_e( 'Devam ediyor', 'is-press' ); ?></option>
				<option value="completed" <?php selected( $status, 'completed' ); ?>><?php esc_html_e( 'Tamamlandı', 'is-press' ); ?></option>
			</select>
		</p>
		<?php
	}

	/**
	 * Save validated task metadata.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Current post.
	 */
	public static function save_task( $post_id, $post ) {
		if (
			! isset( $_POST['is_press_task_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['is_press_task_nonce'] ) ), 'is_press_save_task' )
			|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			|| wp_is_post_revision( $post_id )
			|| ! current_user_can( 'edit_post', $post_id )
		) {
			return;
		}

		if ( isset( $_POST['is_press_due_date'] ) ) {
			$due_date = sanitize_text_field( wp_unslash( $_POST['is_press_due_date'] ) );
			if ( self::is_valid_date( $due_date ) ) {
				update_post_meta( $post_id, self::META_DUE_DATE, $due_date );
			} else {
				delete_post_meta( $post_id, self::META_DUE_DATE );
			}
		}

		if ( isset( $_POST['is_press_status'] ) ) {
			$status = sanitize_key( wp_unslash( $_POST['is_press_status'] ) );
			if ( in_array( $status, array( 'planned', 'in_progress', 'completed' ), true ) ) {
				update_post_meta( $post_id, self::META_STATUS, $status );
			}
		}

		if ( isset( $_POST['is_press_reminder'] ) ) {
			$reminder_input = sanitize_text_field( wp_unslash( $_POST['is_press_reminder'] ) );
			$reminder       = self::parse_reminder( $reminder_input );
			$old_reminder   = (int) get_post_meta( $post_id, self::META_REMINDER, true );

			if ( $reminder ) {
				update_post_meta( $post_id, self::META_REMINDER, $reminder );
				if ( $reminder !== $old_reminder ) {
					delete_post_meta( $post_id, self::META_REMINDER_SENT );
				}
			} else {
				delete_post_meta( $post_id, self::META_REMINDER );
				delete_post_meta( $post_id, self::META_REMINDER_SENT );
			}
		}
	}

	/**
	 * Check that a date input represents a real calendar date.
	 *
	 * @param string $value Date string.
	 * @return bool
	 */
	private static function is_valid_date( $value ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return false;
		}

		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, wp_timezone() );
		return $date && $date->format( 'Y-m-d' ) === $value;
	}

	/**
	 * Convert a local datetime input into a Unix timestamp.
	 *
	 * @param string $value Datetime-local input.
	 * @return int|false
	 */
	private static function parse_reminder( $value ) {
		if ( '' === $value ) {
			return false;
		}

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value ) ) {
			return false;
		}

		$date   = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $value, wp_timezone() );
		$errors = DateTimeImmutable::getLastErrors();
		if (
			! $date
			|| ( false !== $errors && ( $errors['warning_count'] || $errors['error_count'] ) )
			|| $date->format( 'Y-m-d\TH:i' ) !== $value
		) {
			return false;
		}

		return $date->getTimestamp();
	}

	/**
	 * Add useful task details to the admin list.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public static function add_list_columns( $columns ) {
		$columns['is_press_due_date'] = __( 'Son tarih', 'is-press' );
		$columns['is_press_status']  = __( 'Görev durumu', 'is-press' );
		$columns['is_press_reminder'] = __( 'Hatırlatma', 'is-press' );
		return $columns;
	}

	/**
	 * Render values in the admin task list.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public static function render_list_column( $column, $post_id ) {
		if ( 'is_press_due_date' === $column ) {
			$date = get_post_meta( $post_id, self::META_DUE_DATE, true );
			$date_object = $date ? DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() ) : false;
			echo $date_object ? esc_html( wp_date( get_option( 'date_format' ), $date_object->getTimestamp(), wp_timezone() ) ) : '&mdash;';
		} elseif ( 'is_press_status' === $column ) {
			$labels = array(
				'planned'     => __( 'Planlandı', 'is-press' ),
				'in_progress' => __( 'Devam ediyor', 'is-press' ),
				'completed'   => __( 'Tamamlandı', 'is-press' ),
			);
			$status = get_post_meta( $post_id, self::META_STATUS, true );
			echo esc_html( isset( $labels[ $status ] ) ? $labels[ $status ] : $labels['planned'] );
		} elseif ( 'is_press_reminder' === $column ) {
			$reminder = (int) get_post_meta( $post_id, self::META_REMINDER, true );
			echo $reminder ? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $reminder, wp_timezone() ) ) : '&mdash;';
		}
	}

	/**
	 * Ensure the hourly reminder event exists.
	 */
	public static function schedule_reminders() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', self::CRON_HOOK );
		}
	}

	/**
	 * Send due reminders once for each unfinished task.
	 */
	public static function send_due_reminders() {
		$tasks = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'private' ),
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => self::META_REMINDER,
						'value'   => time(),
						'compare' => '<=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		foreach ( $tasks as $task_id ) {
			if (
				'completed' === get_post_meta( $task_id, self::META_STATUS, true )
				|| get_post_meta( $task_id, self::META_REMINDER_SENT, true )
			) {
				continue;
			}

			$task  = get_post( $task_id );
			$author = $task ? get_user_by( 'id', $task->post_author ) : false;
			$email  = $author ? $author->user_email : get_option( 'admin_email' );
			$title  = $task ? wp_strip_all_tags( get_the_title( $task ) ) : '';
			$url    = get_edit_post_link( $task_id, 'raw' );
			$subject = sprintf(
				/* translators: %s: task title. */
				__( 'IS-Press görev hatırlatması: %s', 'is-press' ),
				$title
			);
			$message = sprintf(
				/* translators: 1: task title, 2: task edit URL. */
				__( 'Hatırlatma zamanı gelen iş planınız: %1$s. Görevi görüntülemek ve güncellemek için: %2$s', 'is-press' ),
				$title,
				$url
			);

			if ( $email && wp_mail( $email, $subject, $message ) ) {
				update_post_meta( $task_id, self::META_REMINDER_SENT, 1 );
			}
		}
	}

	/**
	 * Schedule the recurring task when the plugin is activated.
	 */
	public static function activate() {
		self::register_post_type();
		self::schedule_reminders();
		flush_rewrite_rules();
	}

	/**
	 * Remove scheduled reminders on deactivation.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		flush_rewrite_rules();
	}
}

IS_Press::init();
register_activation_hook( __FILE__, array( 'IS_Press', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'IS_Press', 'deactivate' ) );
