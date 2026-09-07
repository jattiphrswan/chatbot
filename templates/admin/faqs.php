<?php
/**
 * Admin FAQs Management Template.
 *
 * @package SkyFish\GeminiChat\Templates\Admin
 *
 * @var array<string, mixed> $pagination   Pagination data array.
 * @var array<int, array>    $categories   Distinct category names.
 * @var array<string, mixed>|null $editing_faq FAQ being edited or null.
 * @var string               $base_url     Base admin URL.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items       = $pagination['items'] ?? [];
$total_items = (int) ( $pagination['total'] ?? 0 );
$total_pages = (int) ( $pagination['pages'] ?? 1 );
$page        = (int) ( $pagination['page'] ?? 1 );

$is_editing = ! empty( $editing_faq );
$search_val = isset( $_GET['s'] ) ? sanitize_text_field( (string) $_GET['s'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$cat_val    = isset( $_GET['category'] ) ? sanitize_text_field( (string) $_GET['category'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>

<div class="wrap gca-admin-wrap">
	<header class="gca-admin-header">
		<div class="gca-admin-header__title-area">
			<h1 class="gca-admin-header__title">
				<span class="dashicons dashicons-format-chat" aria-hidden="true"></span>
				<?php esc_html_e( 'FAQ Management', 'gemini-chat-assistant' ); ?>
			</h1>
			<p class="gca-admin-header__desc">
				<?php esc_html_e( 'Create and manage frequently asked questions. Active FAQs marked for Home appear directly in the public chatbot widget without consuming Gemini tokens.', 'gemini-chat-assistant' ); ?>
			</p>
		</div>
		<div class="gca-admin-header__actions">
			<?php if ( $is_editing ) : ?>
				<a href="<?php echo esc_url( $base_url ); ?>" class="button">
					<?php esc_html_e( '← Back to All FAQs', 'gemini-chat-assistant' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</header>

	<?php if ( isset( $_GET['created'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'FAQ successfully created and indexed.', 'gemini-chat-assistant' ); ?></p>
		</div>
	<?php elseif ( isset( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'FAQ successfully updated and re-indexed.', 'gemini-chat-assistant' ); ?></p>
		</div>
	<?php elseif ( isset( $_GET['deleted'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'FAQ deleted and removed from knowledge index.', 'gemini-chat-assistant' ); ?></p>
		</div>
	<?php elseif ( isset( $_GET['toggled'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'FAQ active status updated.', 'gemini-chat-assistant' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="gca-admin-grid gca-admin-grid--faqs" style="display: grid; grid-template-columns: <?php echo $is_editing ? '1fr' : '1fr 2fr'; ?>; gap: 24px; align-items: start;">
		<!-- Column 1: Add / Edit FAQ Form -->
		<div class="gca-admin-card gca-faq-form-card">
			<h2 class="gca-admin-card__title">
				<?php echo $is_editing ? esc_html__( 'Edit FAQ', 'gemini-chat-assistant' ) : esc_html__( 'Add New FAQ', 'gemini-chat-assistant' ); ?>
			</h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php if ( $is_editing ) : ?>
					<input type="hidden" name="action" value="gca_update_faq" />
					<input type="hidden" name="faq_id" value="<?php echo esc_attr( (string) $editing_faq['public_id'] ); ?>" />
					<?php wp_nonce_field( 'gca_update_faq_' . $editing_faq['public_id'] ); ?>
				<?php else : ?>
					<input type="hidden" name="action" value="gca_create_faq" />
					<?php wp_nonce_field( 'gca_create_faq' ); ?>
				<?php endif; ?>

				<div class="gca-form-field" style="margin-bottom: 16px;">
					<label for="gca-faq-question" style="display: block; font-weight: 600; margin-bottom: 4px;">
						<?php esc_html_e( 'Question', 'gemini-chat-assistant' ); ?> <span class="required" style="color: #dc2626;">*</span>
					</label>
					<input
						type="text"
						id="gca-faq-question"
						name="question"
						class="regular-text"
						style="width: 100%; max-width: 100%;"
						value="<?php echo $is_editing ? esc_attr( (string) $editing_faq['question'] ) : ''; ?>"
						required
						maxlength="500"
						placeholder="<?php esc_attr_e( 'e.g., What are your shipping times?', 'gemini-chat-assistant' ); ?>"
					/>
				</div>

				<div class="gca-form-field" style="margin-bottom: 16px;">
					<label for="gca-faq-answer" style="display: block; font-weight: 600; margin-bottom: 4px;">
						<?php esc_html_e( 'Answer', 'gemini-chat-assistant' ); ?> <span class="required" style="color: #dc2626;">*</span>
					</label>
					<textarea
						id="gca-faq-answer"
						name="answer"
						rows="6"
						style="width: 100%; max-width: 100%; font-family: inherit;"
						required
						maxlength="10000"
						placeholder="<?php esc_attr_e( 'Enter the clear, helpful answer here...', 'gemini-chat-assistant' ); ?>"
					><?php echo $is_editing ? esc_textarea( (string) $editing_faq['answer'] ) : ''; ?></textarea>
					<p class="description" style="margin-top: 4px; font-size: 12px; color: #64748b;">
						<?php esc_html_e( 'Plain text or formatted paragraphs. Rendered securely in the widget.', 'gemini-chat-assistant' ); ?>
					</p>
				</div>

				<div class="gca-form-field" style="margin-bottom: 16px;">
					<label for="gca-faq-category" style="display: block; font-weight: 600; margin-bottom: 4px;">
						<?php esc_html_e( 'Category', 'gemini-chat-assistant' ); ?>
					</label>
					<input
						type="text"
						id="gca-faq-category"
						name="category"
						class="regular-text"
						style="width: 100%; max-width: 100%;"
						value="<?php echo $is_editing ? esc_attr( (string) ( $editing_faq['category'] ?? '' ) ) : ''; ?>"
						placeholder="<?php esc_attr_e( 'e.g., Shipping, Returns, General', 'gemini-chat-assistant' ); ?>"
					/>
				</div>

				<div class="gca-form-field" style="margin-bottom: 16px;">
					<label for="gca-faq-sort-order" style="display: block; font-weight: 600; margin-bottom: 4px;">
						<?php esc_html_e( 'Sort Order', 'gemini-chat-assistant' ); ?>
					</label>
					<input
						type="number"
						id="gca-faq-sort-order"
						name="sort_order"
						value="<?php echo $is_editing ? esc_attr( (string) ( $editing_faq['sort_order'] ?? 0 ) ) : '0'; ?>"
						style="width: 100px;"
					/>
					<p class="description" style="font-size: 12px; color: #64748b;">
						<?php esc_html_e( 'Lower numbers appear first.', 'gemini-chat-assistant' ); ?>
					</p>
				</div>

				<div class="gca-form-field" style="margin-bottom: 12px;">
					<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
						<input
							type="checkbox"
							name="is_active"
							value="1"
							<?php checked( ! $is_editing || ! empty( $editing_faq['is_active'] ) ); ?>
						/>
						<span><strong><?php esc_html_e( 'Active', 'gemini-chat-assistant' ); ?></strong> (<?php esc_html_e( 'Enable this FAQ', 'gemini-chat-assistant' ); ?>)</span>
					</label>
				</div>

				<div class="gca-form-field" style="margin-bottom: 24px;">
					<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
						<input
							type="checkbox"
							name="show_on_home"
							value="1"
							<?php checked( $is_editing && ! empty( $editing_faq['show_on_home'] ) ); ?>
						/>
						<span><strong><?php esc_html_e( 'Show on Chatbot Home', 'gemini-chat-assistant' ); ?></strong> (<?php esc_html_e( 'Display in Quick Help', 'gemini-chat-assistant' ); ?>)</span>
					</label>
				</div>

				<div class="gca-form-actions" style="display: flex; gap: 8px;">
					<button type="submit" class="button button-primary">
						<?php echo $is_editing ? esc_html__( 'Update FAQ', 'gemini-chat-assistant' ) : esc_html__( 'Save FAQ', 'gemini-chat-assistant' ); ?>
					</button>
					<?php if ( $is_editing ) : ?>
						<a href="<?php echo esc_url( $base_url ); ?>" class="button">
							<?php esc_html_e( 'Cancel', 'gemini-chat-assistant' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</form>
		</div>

		<?php if ( ! $is_editing ) : ?>
		<!-- Column 2: Existing FAQs List Table -->
		<div class="gca-admin-card gca-faq-list-card">
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
				<h2 class="gca-admin-card__title" style="margin: 0;">
					<?php esc_html_e( 'Existing FAQs', 'gemini-chat-assistant' ); ?>
					<span style="font-size: 14px; font-weight: normal; color: #64748b;">(<?php echo esc_html( (string) $total_items ); ?>)</span>
				</h2>

				<!-- Search and Filter Form -->
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display: flex; gap: 8px; align-items: center;">
					<input type="hidden" name="page" value="<?php echo esc_attr( self::FAQS_MENU_SLUG ); ?>" />

					<?php if ( ! empty( $categories ) ) : ?>
						<select name="category" onchange="this.form.submit()">
							<option value=""><?php esc_html_e( 'All Categories', 'gemini-chat-assistant' ); ?></option>
							<?php foreach ( $categories as $cat ) : ?>
								<option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $cat_val, $cat ); ?>>
									<?php echo esc_html( $cat ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					<?php endif; ?>

					<input
						type="search"
						name="s"
						value="<?php echo esc_attr( $search_val ); ?>"
						placeholder="<?php esc_attr_e( 'Search FAQs...', 'gemini-chat-assistant' ); ?>"
						style="width: 180px;"
					/>
					<button type="submit" class="button"><?php esc_html_e( 'Filter', 'gemini-chat-assistant' ); ?></button>
					<?php if ( '' !== $search_val || '' !== $cat_val ) : ?>
						<a href="<?php echo esc_url( $base_url ); ?>" class="button">✕</a>
					<?php endif; ?>
				</form>
			</div>

			<?php if ( empty( $items ) ) : ?>
				<div style="text-align: center; padding: 32px 16px; color: #64748b;">
					<span class="dashicons dashicons-editor-help" style="font-size: 36px; height: 36px; width: 36px; margin-bottom: 8px; opacity: 0.5;"></span>
					<p><?php esc_html_e( 'No FAQs found. Create your first FAQ using the form.', 'gemini-chat-assistant' ); ?></p>
				</div>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width: 40%;"><?php esc_html_e( 'Question', 'gemini-chat-assistant' ); ?></th>
							<th style="width: 15%;"><?php esc_html_e( 'Category', 'gemini-chat-assistant' ); ?></th>
							<th style="width: 10%; text-align: center;"><?php esc_html_e( 'Status', 'gemini-chat-assistant' ); ?></th>
							<th style="width: 10%; text-align: center;"><?php esc_html_e( 'Home', 'gemini-chat-assistant' ); ?></th>
							<th style="width: 8%; text-align: center;"><?php esc_html_e( 'Order', 'gemini-chat-assistant' ); ?></th>
							<th style="width: 17%; text-align: right;"><?php esc_html_e( 'Actions', 'gemini-chat-assistant' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $items as $faq ) : ?>
							<tr>
								<td>
									<strong>
										<a href="<?php echo esc_url( add_query_arg( [ 'action' => 'edit', 'faq_id' => $faq['public_id'] ], $base_url ) ); ?>">
											<?php echo esc_html( (string) $faq['question'] ); ?>
										</a>
									</strong>
									<div style="font-size: 12px; color: #64748b; margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
										<?php echo esc_html( wp_trim_words( (string) $faq['answer'], 15 ) ); ?>
									</div>
								</td>
								<td>
									<?php if ( ! empty( $faq['category'] ) ) : ?>
										<span class="gca-admin-pill" style="background: #e0e7ff; color: #3730a3; padding: 2px 8px; border-radius: 4px; font-size: 12px;">
											<?php echo esc_html( (string) $faq['category'] ); ?>
										</span>
									<?php else : ?>
										<span style="color: #94a3b8;">—</span>
									<?php endif; ?>
								</td>
								<td style="text-align: center;">
									<?php if ( ! empty( $faq['is_active'] ) ) : ?>
										<span style="color: #16a34a; font-weight: 600; font-size: 12px;">● <?php esc_html_e( 'Active', 'gemini-chat-assistant' ); ?></span>
									<?php else : ?>
										<span style="color: #94a3b8; font-size: 12px;">○ <?php esc_html_e( 'Inactive', 'gemini-chat-assistant' ); ?></span>
									<?php endif; ?>
								</td>
								<td style="text-align: center;">
									<?php if ( ! empty( $faq['show_on_home'] ) ) : ?>
										<span class="dashicons dashicons-yes-alt" style="color: #64258a;" title="<?php esc_attr_e( 'Visible on Home Screen', 'gemini-chat-assistant' ); ?>"></span>
									<?php else : ?>
										<span style="color: #cbd5e1;">—</span>
									<?php endif; ?>
								</td>
								<td style="text-align: center;">
									<code><?php echo esc_html( (string) ( $faq['sort_order'] ?? 0 ) ); ?></code>
								</td>
								<td style="text-align: right;">
									<div style="display: flex; gap: 8px; justify-content: flex-end; align-items: center;">
										<a href="<?php echo esc_url( add_query_arg( [ 'action' => 'edit', 'faq_id' => $faq['public_id'] ], $base_url ) ); ?>" class="button button-small">
											<?php esc_html_e( 'Edit', 'gemini-chat-assistant' ); ?>
										</a>

										<!-- Toggle Active Form -->
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
											<input type="hidden" name="action" value="gca_toggle_faq_active" />
											<input type="hidden" name="faq_id" value="<?php echo esc_attr( (string) $faq['public_id'] ); ?>" />
											<?php wp_nonce_field( 'gca_toggle_faq_' . $faq['public_id'] ); ?>
											<button type="submit" class="button button-small" title="<?php esc_attr_e( 'Toggle active state', 'gemini-chat-assistant' ); ?>">
												<?php echo ! empty( $faq['is_active'] ) ? esc_html__( 'Disable', 'gemini-chat-assistant' ) : esc_html__( 'Enable', 'gemini-chat-assistant' ); ?>
											</button>
										</form>

										<!-- Delete Form -->
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;" onsubmit="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this FAQ?', 'gemini-chat-assistant' ); ?>');">
											<input type="hidden" name="action" value="gca_delete_faq" />
											<input type="hidden" name="faq_id" value="<?php echo esc_attr( (string) $faq['public_id'] ); ?>" />
											<?php wp_nonce_field( 'gca_delete_faq_' . $faq['public_id'] ); ?>
											<button type="submit" class="button button-small button-link-delete" style="color: #dc2626;">
												<?php esc_html_e( 'Delete', 'gemini-chat-assistant' ); ?>
											</button>
										</form>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<!-- Pagination -->
				<?php if ( $total_pages > 1 ) : ?>
					<div class="tablenav" style="margin-top: 16px;">
						<div class="tablenav-pages">
							<span class="displaying-num"><?php echo sprintf( esc_html__( '%s items', 'gemini-chat-assistant' ), number_format_i18n( $total_items ) ); ?></span>
							<?php
							echo paginate_links( [
								'base'      => add_query_arg( 'paged', '%#%', $base_url ),
								'format'    => '',
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
								'total'     => $total_pages,
								'current'   => $page,
							] );
							?>
						</div>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php endif; ?>
	</div>
</div>
