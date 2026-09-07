<?php
/**
 * Admin Knowledge Base & RAG Management Template.
 *
 * @package SkyFish\GeminiChat\Templates\Admin
 *
 * @var array{sources: int, chunks: int, last_indexed: ?string} $counts       Real statistics.
 * @var array<string, mixed>                                   $settings     Central plugin settings.
 * @var string                                                 $base_url     Base admin URL.
 * @var string                                                 $search_test  Query test search string.
 * @var array<int, array<string, mixed>>                       $test_results Retrieved chunks test.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$knowledge_enabled   = ! empty( $settings['knowledge_enabled'] );
$pages_enabled       = ! empty( $settings['knowledge_pages_enabled'] );
$posts_enabled       = ! empty( $settings['knowledge_posts_enabled'] );
$products_enabled    = ! empty( $settings['knowledge_products_enabled'] );
$faqs_enabled        = ! empty( $settings['knowledge_faqs_enabled'] );
$max_chunks          = absint( $settings['knowledge_max_chunks'] ?? 4 );
$max_context_chars   = absint( $settings['knowledge_max_context_chars'] ?? 6000 );
$wc_active           = function_exists( 'WC' ) || class_exists( 'WooCommerce' );
?>

<div class="wrap gca-admin-wrap">
	<header class="gca-admin-header">
		<div class="gca-admin-header__title-area">
			<h1 class="gca-admin-header__title">
				<span class="dashicons dashicons-book" aria-hidden="true"></span>
				<?php esc_html_e( 'Website Knowledge & RAG Grounding', 'gemini-chat-assistant' ); ?>
			</h1>
			<p class="gca-admin-header__desc">
				<?php esc_html_e( 'Ground Gemini conversational answers with real content from your WordPress website using safe, deterministic local retrieval.', 'gemini-chat-assistant' ); ?>
			</p>
		</div>
	</header>

	<?php if ( isset( $_GET['synced'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				$synced_items  = isset( $_GET['indexed'] ) ? absint( $_GET['indexed'] ) : 0;
				$total_chunks  = isset( $_GET['chunks'] ) ? absint( $_GET['chunks'] ) : 0;
				printf(
					/* translators: 1: number of sources indexed, 2: total chunks in database */
					esc_html__( 'Knowledge sync complete! %1$d sources processed. Total chunks in index: %2$d.', 'gemini-chat-assistant' ),
					$synced_items,
					$total_chunks
				);
				?>
			</p>
		</div>
	<?php elseif ( isset( $_GET['cleared'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Knowledge index cleared. Sources and chunks removed without affecting WordPress posts, pages, or FAQs.', 'gemini-chat-assistant' ); ?></p>
		</div>
	<?php elseif ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Knowledge settings successfully saved.', 'gemini-chat-assistant' ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Metrics Overview Cards -->
	<div class="gca-admin-grid gca-admin-grid--stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
		<div class="gca-admin-card" style="padding: 20px;">
			<div style="font-size: 13px; color: #64748b; margin-bottom: 6px;"><?php esc_html_e( 'Knowledge Status', 'gemini-chat-assistant' ); ?></div>
			<div style="font-size: 20px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
				<?php if ( $knowledge_enabled ) : ?>
					<span style="color: #16a34a;">● <?php esc_html_e( 'Active / Enabled', 'gemini-chat-assistant' ); ?></span>
				<?php else : ?>
					<span style="color: #94a3b8;">○ <?php esc_html_e( 'Disabled', 'gemini-chat-assistant' ); ?></span>
				<?php endif; ?>
			</div>
			<p style="font-size: 12px; color: #64748b; margin: 6px 0 0 0;">
				<?php echo $knowledge_enabled ? esc_html__( 'Grounding Gemini with retrieved chunks', 'gemini-chat-assistant' ) : esc_html__( 'Enable below to ground answers', 'gemini-chat-assistant' ); ?>
			</p>
		</div>

		<div class="gca-admin-card" style="padding: 20px;">
			<div style="font-size: 13px; color: #64748b; margin-bottom: 6px;"><?php esc_html_e( 'Indexed Sources', 'gemini-chat-assistant' ); ?></div>
			<div style="font-size: 28px; font-weight: 700; color: #1e293b;">
				<?php echo esc_html( number_format_i18n( (int) $counts['sources'] ) ); ?>
			</div>
			<p style="font-size: 12px; color: #64748b; margin: 6px 0 0 0;">
				<?php esc_html_e( 'Pages, posts, products, and FAQs', 'gemini-chat-assistant' ); ?>
			</p>
		</div>

		<div class="gca-admin-card" style="padding: 20px;">
			<div style="font-size: 13px; color: #64748b; margin-bottom: 6px;"><?php esc_html_e( 'Knowledge Chunks', 'gemini-chat-assistant' ); ?></div>
			<div style="font-size: 28px; font-weight: 700; color: #1e293b;">
				<?php echo esc_html( number_format_i18n( (int) $counts['chunks'] ) ); ?>
			</div>
			<p style="font-size: 12px; color: #64748b; margin: 6px 0 0 0;">
				<?php esc_html_e( 'Text chunks available for retrieval', 'gemini-chat-assistant' ); ?>
			</p>
		</div>

		<div class="gca-admin-card" style="padding: 20px;">
			<div style="font-size: 13px; color: #64748b; margin-bottom: 6px;"><?php esc_html_e( 'Last Synchronized', 'gemini-chat-assistant' ); ?></div>
			<div style="font-size: 16px; font-weight: 600; color: #1e293b; line-height: 28px;">
				<?php echo ! empty( $counts['last_indexed'] ) ? esc_html( (string) $counts['last_indexed'] ) : esc_html__( 'Never synced', 'gemini-chat-assistant' ); ?>
			</div>
			<p style="font-size: 12px; color: #64748b; margin: 6px 0 0 0;">
				<?php esc_html_e( 'Most recent source index timestamp', 'gemini-chat-assistant' ); ?>
			</p>
		</div>
	</div>

	<!-- Main Settings and Actions Layout -->
	<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: start;">
		<!-- Column 1: Settings Form -->
		<div class="gca-admin-card">
			<h2 class="gca-admin-card__title" style="margin-bottom: 16px;">
				<?php esc_html_e( 'Knowledge & Grounding Settings', 'gemini-chat-assistant' ); ?>
			</h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="gca_save_knowledge_settings" />
				<?php wp_nonce_field( 'gca_save_knowledge_settings' ); ?>

				<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
					<label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
						<input
							type="checkbox"
							name="knowledge_enabled"
							value="1"
							<?php checked( $knowledge_enabled ); ?>
							style="width: 18px; height: 18px;"
						/>
						<div>
							<span style="font-size: 15px; font-weight: 700; color: #1e293b; display: block;">
								<?php esc_html_e( 'Enable Website Knowledge Retrieval (RAG)', 'gemini-chat-assistant' ); ?>
							</span>
							<span style="font-size: 13px; color: #64748b;">
								<?php esc_html_e( 'When enabled, relevant content from enabled sources is retrieved and injected into Gemini prompts as untrusted reference context.', 'gemini-chat-assistant' ); ?>
							</span>
						</div>
					</label>
				</div>

				<h3 style="font-size: 15px; margin-bottom: 12px;"><?php esc_html_e( 'Knowledge Sources', 'gemini-chat-assistant' ); ?></h3>
				<div style="margin-bottom: 20px; display: flex; flex-direction: column; gap: 10px;">
					<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
						<input type="checkbox" name="knowledge_pages_enabled" value="1" <?php checked( $pages_enabled ); ?> />
						<span><strong><?php esc_html_e( 'WordPress Pages', 'gemini-chat-assistant' ); ?></strong> (<?php esc_html_e( 'Published, public pages only', 'gemini-chat-assistant' ); ?>)</span>
					</label>

					<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
						<input type="checkbox" name="knowledge_posts_enabled" value="1" <?php checked( $posts_enabled ); ?> />
						<span><strong><?php esc_html_e( 'WordPress Posts', 'gemini-chat-assistant' ); ?></strong> (<?php esc_html_e( 'Published, public posts and articles', 'gemini-chat-assistant' ); ?>)</span>
					</label>

					<label style="display: flex; align-items: center; gap: 8px; cursor: <?php echo $wc_active ? 'pointer' : 'not-allowed'; ?>; opacity: <?php echo $wc_active ? '1' : '0.6'; ?>;">
						<input
							type="checkbox"
							name="knowledge_products_enabled"
							value="1"
							<?php checked( $products_enabled && $wc_active ); ?>
							<?php disabled( ! $wc_active ); ?>
						/>
						<span>
							<strong><?php esc_html_e( 'WooCommerce Products', 'gemini-chat-assistant' ); ?></strong>
							(<?php esc_html_e( 'Title and description only; no live pricing/stock', 'gemini-chat-assistant' ); ?>)
							<?php if ( ! $wc_active ) : ?>
								<em>— <?php esc_html_e( 'WooCommerce not active', 'gemini-chat-assistant' ); ?></em>
							<?php endif; ?>
						</span>
					</label>

					<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
						<input type="checkbox" name="knowledge_faqs_enabled" value="1" <?php checked( $faqs_enabled ); ?> />
						<span><strong><?php esc_html_e( 'Frequently Asked Questions (FAQs)', 'gemini-chat-assistant' ); ?></strong> (<?php esc_html_e( 'Active FAQs from plugin', 'gemini-chat-assistant' ); ?>)</span>
					</label>
				</div>

				<h3 style="font-size: 15px; margin-bottom: 12px;"><?php esc_html_e( 'Retrieval Limits', 'gemini-chat-assistant' ); ?></h3>
				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
					<div>
						<label for="gca-max-chunks" style="display: block; font-weight: 600; margin-bottom: 4px;">
							<?php esc_html_e( 'Maximum Chunks (Top-K)', 'gemini-chat-assistant' ); ?>
						</label>
						<input
							type="number"
							id="gca-max-chunks"
							name="knowledge_max_chunks"
							value="<?php echo esc_attr( (string) $max_chunks ); ?>"
							min="1"
							max="8"
							style="width: 100px;"
						/>
						<p class="description" style="font-size: 12px; color: #64748b;">
							<?php esc_html_e( 'Range 1–8. Default: 4 chunks.', 'gemini-chat-assistant' ); ?>
						</p>
					</div>

					<div>
						<label for="gca-max-chars" style="display: block; font-weight: 600; margin-bottom: 4px;">
							<?php esc_html_e( 'Max Context Budget (Chars)', 'gemini-chat-assistant' ); ?>
						</label>
						<input
							type="number"
							id="gca-max-chars"
							name="knowledge_max_context_chars"
							value="<?php echo esc_attr( (string) $max_context_chars ); ?>"
							min="500"
							max="12000"
							step="500"
							style="width: 120px;"
						/>
						<p class="description" style="font-size: 12px; color: #64748b;">
							<?php esc_html_e( 'Range 500–12,000. Default: 6,000 chars.', 'gemini-chat-assistant' ); ?>
						</p>
					</div>
				</div>

				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save Knowledge Settings', 'gemini-chat-assistant' ); ?>
				</button>
			</form>
		</div>

		<!-- Column 2: Manual Sync and Index Management Actions -->
		<div style="display: flex; flex-direction: column; gap: 20px;">
			<div class="gca-admin-card">
				<h3 style="font-size: 15px; margin-bottom: 8px;">
					<?php esc_html_e( 'Synchronize Knowledge', 'gemini-chat-assistant' ); ?>
				</h3>
				<p style="font-size: 13px; color: #64748b; margin-bottom: 16px;">
					<?php esc_html_e( 'Scan all eligible published pages, posts, and FAQs, calculate content hashes, and index or update chunks.', 'gemini-chat-assistant' ); ?>
				</p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="gca_sync_knowledge" />
					<?php wp_nonce_field( 'gca_sync_knowledge' ); ?>
					<button type="submit" class="button button-secondary" style="width: 100%; text-align: center;">
						<span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
						<?php esc_html_e( 'Index / Sync Knowledge', 'gemini-chat-assistant' ); ?>
					</button>
				</form>
			</div>

			<div class="gca-admin-card">
				<h3 style="font-size: 15px; margin-bottom: 8px; color: #b91c1c;">
					<?php esc_html_e( 'Clear Knowledge Index', 'gemini-chat-assistant' ); ?>
				</h3>
				<p style="font-size: 13px; color: #64748b; margin-bottom: 16px;">
					<?php esc_html_e( 'Wipes all indexed sources and chunks. Does not delete any WordPress pages, posts, or FAQs.', 'gemini-chat-assistant' ); ?>
				</p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php esc_attr_e( 'Are you sure you want to clear the knowledge index? You will need to re-sync to ground Gemini answers.', 'gemini-chat-assistant' ); ?>');">
					<input type="hidden" name="action" value="gca_clear_knowledge" />
					<?php wp_nonce_field( 'gca_clear_knowledge' ); ?>
					<button type="submit" class="button button-link-delete" style="width: 100%; color: #dc2626; border: 1px solid #fecaca; background: #fff5f5; padding: 6px 12px; border-radius: 4px;">
						<?php esc_html_e( 'Clear Index', 'gemini-chat-assistant' ); ?>
					</button>
				</form>
			</div>
		</div>
	</div>

	<!-- Testing Tool: Test Knowledge Search -->
	<div class="gca-admin-card" style="margin-top: 24px;">
		<h2 class="gca-admin-card__title" style="margin-bottom: 8px;">
			<span class="dashicons dashicons-search" style="vertical-align: middle;"></span>
			<?php esc_html_e( 'Test Knowledge Search (Debug Tool)', 'gemini-chat-assistant' ); ?>
		</h2>
		<p style="font-size: 13px; color: #64748b; margin-bottom: 16px;">
			<?php esc_html_e( 'Test the retrieval algorithm by entering a sample visitor query. Shows retrieved chunks and relevance scores without calling Gemini.', 'gemini-chat-assistant' ); ?>
		</p>

		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display: flex; gap: 8px; margin-bottom: 16px; max-width: 600px;">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::KNOWLEDGE_MENU_SLUG ); ?>" />
			<input
				type="text"
				name="test_query"
				value="<?php echo esc_attr( $search_test ); ?>"
				placeholder="<?php esc_attr_e( 'e.g., return policy or shipping times', 'gemini-chat-assistant' ); ?>"
				style="flex: 1;"
			/>
			<button type="submit" class="button"><?php esc_html_e( 'Search Knowledge', 'gemini-chat-assistant' ); ?></button>
			<?php if ( '' !== $search_test ) : ?>
				<a href="<?php echo esc_url( $base_url ); ?>" class="button">✕</a>
			<?php endif; ?>
		</form>

		<?php if ( '' !== $search_test ) : ?>
			<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px;">
				<h3 style="font-size: 14px; margin-top: 0;">
					<?php
					printf(
						/* translators: 1: query, 2: count */
						esc_html__( 'Retrieved chunks for "%1$s" (%2$d found):', 'gemini-chat-assistant' ),
						esc_html( $search_test ),
						count( $test_results )
					);
					?>
				</h3>

				<?php if ( empty( $test_results ) ) : ?>
					<p style="color: #64748b; font-style: italic; margin-bottom: 0;">
						<?php esc_html_e( 'No chunks met the minimum relevance threshold for this query.', 'gemini-chat-assistant' ); ?>
					</p>
				<?php else : ?>
					<div style="display: flex; flex-direction: column; gap: 12px;">
						<?php foreach ( $test_results as $idx => $chunk ) : ?>
							<div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 12px;">
								<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
									<strong style="color: #1e293b;">
										#<?php echo esc_html( (string) ( $idx + 1 ) ); ?>:
										<?php echo esc_html( (string) ( $chunk['title'] ?? '' ) ); ?>
										<span style="font-size: 11px; font-weight: normal; background: #e0e7ff; color: #3730a3; padding: 2px 6px; border-radius: 3px; margin-left: 6px;">
											<?php echo esc_html( strtoupper( (string) ( $chunk['source_type'] ?? '' ) ) ); ?>
										</span>
									</strong>
									<span style="font-size: 12px; font-weight: 600; color: #16a34a;">
										<?php esc_html_e( 'Score:', 'gemini-chat-assistant' ); ?> <?php echo esc_html( (string) ( $chunk['relevance_score'] ?? 0 ) ); ?>
									</span>
								</div>
								<div style="font-size: 13px; color: #334155; line-height: 1.5; white-space: pre-wrap; max-height: 150px; overflow-y: auto; background: #f1f5f9; padding: 8px; border-radius: 4px;">
									<?php echo esc_html( (string) ( $chunk['content'] ?? '' ) ); ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</div>
