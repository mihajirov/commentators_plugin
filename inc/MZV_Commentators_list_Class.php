<?php
/**
 * Adding custom widget for showing Top commentators
 *
 * Class MZV_Commentators_list_Widget
 */
class MZV_Commentators_list extends WP_Widget {

	/**
	 * Widget name
	 *
	 * @var string
	 */
	protected $mzv_widget_id = 'mzv_commentators_list';

	/**
	 * Init registering widget process
	 */
	function __construct() {

		parent::__construct(
			$this->mzv_widget_id, // widget's ID
			esc_html__( 'Top Commentators List', 'mzv_top_commentators' ),
			array( 'description' => esc_html__( 'Show top commentators List', 'mzv_top_commentators' ), )
		);

		add_action( 'widgets_init', [ $this, 'mzv_commentators_widget_register' ] );
	}

	/**
	 * Registering widget in WordPress by custom id
	 */
	public function mzv_commentators_widget_register() {
		register_widget( $this->mzv_widget_id );
	}

	/**
	 * Front-end part displaying
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {

		global $wpdb;
		$prefix = $wpdb->prefix;

		// condition for commentators amount to show
		if ( (int)$instance['commentators_amount'] < 1 ) {
			$limit_commentators = 'LIMIT 0';
		} else {
			$limit_commentators = 'LIMIT ' . $instance['commentators_amount'];
		}

		// show commentators by specific user role/s
		if ( is_array( $instance['user_roles_to_show'] ) && !empty( $instance['user_roles_to_show'] ) ) {

			// if only 1 user role was selected in admin panel - lets crete only 1 ow with query
			if ( 1 === count( $instance['user_roles_to_show'] ) ) {
				$roles_sub_and = "`{$prefix}usermeta`.`meta_value` LIKE '%{$instance['user_roles_to_show'][0]}%'";
				if ( 'all' === $instance['user_roles_to_show'][0] ) {
					$roles_sub_and = ' 1=1 ';
				}
			} else {

				// if more than 1 user role was selected in admin panel - creating OR condition for each of them
				$roles_sub_and = '';
				foreach ( $instance['user_roles_to_show'] as $key => $role ) {
					$roles_sub_and .= "`{$prefix}usermeta`.`meta_value` LIKE '%{$role}%'";
					if ( $key !== ( count( $instance['user_roles_to_show'] ) - 1 ) ) {
						$roles_sub_and .= ' OR ';
					}
				}
			}

			// combining dnamically sql queries rows for showing commentators by specific roles
		    $roles_and = "AND tu.`ID` IN (
            SELECT `{$prefix}usermeta`.`user_id` FROM `{$prefix}usermeta` WHERE `{$prefix}usermeta`.`meta_key` = '{$prefix}capabilities'  
            AND ( {$roles_sub_and} ) )";
        } else {
			$roles_and = "";
		}

		// main query for showing commentators by all admin options
		$user_and_comments = $wpdb->get_results(
	"	SELECT tu.`ID`, tu.`user_login`, COUNT(*) AS NUM FROM `{$prefix}users` AS tu
			INNER JOIN `{$prefix}comments` AS tc
			ON tu.`ID` = tc.`user_id`
			WHERE tc.`comment_type` NOT LIKE 'pingback'
			AND tc.`comment_type` NOT LIKE 'trackback'
			AND tc.`comment_approved` = '1'
			{$roles_and}
			GROUP BY tu.`ID` ORDER BY COUNT(*) DESC {$limit_commentators}"
		);

		// showing users without any comments at the end of the list, because at first going users with comments, sorted by comments count
		$user_without_comments = [];
		if ( 'on' === $instance['commentators_without_comments'] && count( $user_and_comments ) < (int) $instance['commentators_amount'] ) {
			$how_many_show_wo_comments = (int) $instance['commentators_amount'] - count( $user_and_comments );
			$user_without_comments     = $wpdb->get_results(
				"
					SELECT tu.`ID`, tu.`user_login`FROM `{$prefix}users` AS tu
					WHERE tu.`ID` NOT IN (SELECT `{$prefix}comments`.`user_id` FROM `{$prefix}comments`)
					ORDER BY tu.`ID` DESC LIMIT {$how_many_show_wo_comments}"
			);
		}

		// simple front-end html widget - will be shown at site styling
		echo '<ul class="sidebar-commenters-list">';

			foreach ( $user_and_comments as $comments_data ) :
			?>
				<li>
					<?php echo $comments_data->user_login . ' '; ?>
					<?php echo ( 'yes' === $instance['show_comments'] ) ? '(' . $comments_data->NUM . ')' : ''; ?>
				</li>
			<?php
			endforeach;

			if ( !empty( $user_without_comments ) ) {
				foreach ( $user_without_comments as $user_item ) {
					echo '<li>' . $user_item->user_login . '</li>';
				}
			}

		echo '</ul>';
	}

	/**
	 * Back-end part of widget
	 *
	 * @param array $instance
	 *
	 * @return string|void
	 */
	public function form( $instance ) {
		$show_comments                 = ! empty( $instance['show_comments'] ) ? $instance['show_comments'] : 'No';
		$comentators_amount            = ! empty( $instance['commentators_amount'] ) ? $instance['commentators_amount'] : '-1';
		$commentators_without_comments = ! empty( $instance['commentators_without_comments'] ) ? $instance['commentators_without_comments'] : 'off';
		$user_roles_to_show            = ! empty( $instance['user_roles_to_show'] ) ? $instance['user_roles_to_show'] : 'all';
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_comments' ) ); ?>">
				<?php esc_attr_e( 'Показати кількість коментарів користувача:', 'mzv_top_commentators' ); ?>
			</label>
			<select name="<?php echo esc_attr( $this->get_field_name( 'show_comments' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'show_comments' ) ); ?>">
				<?php
				$options_list = [
					'no' => 'Ні',
					'yes' => 'Так',
				];
				?>
				<?php foreach ( $options_list as $key => $item ) : ?>
					<option value="<?php echo $key; ?>" <?php echo ( $show_comments === $key ) ? 'selected' : ''; ?>><?php echo __( $item, 'mzv_top_commentators' ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'commentators_amount' ) ); ?>">
				<?php esc_attr_e( 'Кількість коментаторів для показу:', 'mzv_top_commentators' ); ?>
			</label>
			<input type="number" min="0" max="100" value="<?php echo $comentators_amount; ?>"
			       name="<?php echo esc_attr( $this->get_field_name( 'commentators_amount' ) ); ?>"
			       id="<?php echo esc_attr( $this->get_field_id( 'commentators_amount' ) ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'commentators_without_comments' ) ); ?>">
				<input type="checkbox" <?php echo ( 'on' === $commentators_without_comments ) ? 'checked' : ''; ?>
				name="<?php echo esc_attr( $this->get_field_name( 'commentators_without_comments' ) ); ?>"
				id="<?php echo esc_attr( $this->get_field_id( 'commentators_without_comments' ) ); ?>">

				<?php esc_attr_e( 'Показати юзерів без коментарів:', 'mzv_top_commentators' ); ?>
			</label>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'user_roles_to_show' ) ); ?>">
				<?php esc_attr_e( 'Вибрати роль користувача для показу:', 'mzv_top_commentators' ); ?>
				<br><i>CTRL + click - щоб вибрати декілька ролей</i>
			</label>
			<select name="<?php echo esc_attr( $this->get_field_name( 'user_roles_to_show' ) ); ?>[]"
			        id="<?php echo esc_attr( $this->get_field_id( 'user_roles_to_show' ) ); ?>" multiple>

				<option value="all" <?php echo ( in_array( 'all', $user_roles_to_show ) ) ? 'selected' : ''; ?>>
					<?php esc_attr_e( 'Усі', 'mzv_top_commentators' ); ?>
				</option>
				<?php
				global $wp_roles;
				$all_roles = $wp_roles->roles;
				?>
				<?php foreach ( $all_roles as $key => $item ) : ?>
					<option value="<?php echo $key; ?>" <?php echo ( in_array( $key, $user_roles_to_show ) ) ? 'selected' : ''; ?>><?php echo $key; ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * Sanitize widget data, saving
	 *
	 * @param array $new_instance
	 * @param array $old_instance
	 *
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {

		$instance                                  = array();
		$instance['show_comments']                 = ( ! empty( $new_instance['show_comments'] ) ) ? sanitize_text_field( $new_instance['show_comments'] ) : '';
		$instance['commentators_amount']           = ( ! empty( $new_instance['commentators_amount'] ) ) ? sanitize_text_field( $new_instance['commentators_amount'] ) : '-1';
		$instance['commentators_without_comments'] = ( ! empty( $new_instance['commentators_without_comments'] ) ) ? $new_instance['commentators_without_comments'] : 'off';

		if ( is_array( $new_instance['user_roles_to_show'] ) ) {
			foreach ( $new_instance['user_roles_to_show'] as $item ) {
				$instance['user_roles_to_show'][] = $item;
			}
		}

		return $instance;
	}

}

new MZV_Commentators_list();
