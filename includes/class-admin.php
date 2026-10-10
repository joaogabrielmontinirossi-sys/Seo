<?php
/**
 * Interface administrativa: painel no editor, colunas, campos de termos e configurações.
 *
 * @package SeoLegibilidade
 */

namespace SeoLegibilidade;

defined( 'ABSPATH' ) || exit;

class Admin {

	const NONCE = 'seol_save_meta';

	public static function register() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save_post' ), 10, 2 );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'apply_slug' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SEOL_FILE ), array( __CLASS__, 'action_links' ) );

		foreach ( self::post_types() as $type ) {
			add_filter( "manage_{$type}_posts_columns", array( __CLASS__, 'columns' ) );
			add_action( "manage_{$type}_posts_custom_column", array( __CLASS__, 'column' ), 10, 2 );
		}
		add_action( 'init', array( __CLASS__, 'term_hooks' ), 99 );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'dashboard_widget' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Painel: conteúdo para atualizar                                     */
	/* ------------------------------------------------------------------ */

	public static function dashboard_widget() {
		if ( current_user_can( 'edit_posts' ) ) {
			wp_add_dashboard_widget( 'seol_stale', __( 'Conteúdo para atualizar', 'seo-legibilidade' ), array( __CLASS__, 'render_dashboard_widget' ) );
		}
	}

	/**
	 * Posts publicados com ano vencido no título ou sem atualização há mais de um ano.
	 *
	 * @return array<int, array{post:\WP_Post, reason:string}>
	 */
	public static function stale_posts( $limit = 10 ) {
		$current = (int) wp_date( 'Y' );
		$out     = array();
		$recent  = get_posts(
			array(
				'post_type'      => self::post_types(),
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		foreach ( $recent as $p ) {
			$titles = $p->post_title . ' ' . Meta::post( $p->ID, 'title' );
			$past   = array_filter(
				Analysis\Quality_Analyzer::years( $titles ),
				static function ( $y ) use ( $current ) {
					return $y < $current;
				}
			);
			if ( $past ) {
				/* translators: %s: anos. */
				$out[ $p->ID ] = array( 'post' => $p, 'reason' => sprintf( __( 'título cita %s', 'seo-legibilidade' ), implode( ', ', array_unique( $past ) ) ) );
			}
		}
		$old = get_posts(
			array(
				'post_type'      => self::post_types(),
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'modified',
				'order'          => 'ASC',
				'date_query'     => array(
					array(
						'column' => 'post_modified_gmt',
						'before' => gmdate( 'Y-m-d H:i:s', time() - Analysis\Quality_Analyzer::STALE_DAYS * DAY_IN_SECONDS ),
					),
				),
			)
		);
		foreach ( $old as $p ) {
			if ( ! isset( $out[ $p->ID ] ) ) {
				/* translators: %s: data. */
				$out[ $p->ID ] = array( 'post' => $p, 'reason' => sprintf( __( 'sem atualização desde %s', 'seo-legibilidade' ), get_the_modified_date( '', $p ) ) );
			}
		}
		return array_slice( array_values( $out ), 0, $limit );
	}

	public static function render_dashboard_widget() {
		$items = self::stale_posts();
		if ( ! $items ) {
			echo '<p>' . esc_html__( 'Tudo em dia: nenhum conteúdo com ano vencido no título ou sem atualização há mais de um ano.', 'seo-legibilidade' ) . '</p>';
			return;
		}
		echo '<p>' . esc_html__( 'Revisar estes conteúdos (dados, preços, links e ano no título) ajuda a manter as posições no Google:', 'seo-legibilidade' ) . '</p><ul>';
		foreach ( $items as $item ) {
			printf(
				'<li><a href="%s">%s</a> — <span class="description">%s</span></li>',
				esc_url( get_edit_post_link( $item['post']->ID ) ),
				esc_html( get_the_title( $item['post'] ) ),
				esc_html( $item['reason'] )
			);
		}
		echo '</ul>';
	}

	public static function post_types() {
		$types = get_post_types( array( 'public' => true ), 'names' );
		unset( $types['attachment'] );
		return array_values( $types );
	}

	/* ------------------------------------------------------------------ */
	/* Painel do editor                                                    */
	/* ------------------------------------------------------------------ */

	public static function add_meta_box() {
		add_meta_box( 'seol-box', __( 'SEO & Legibilidade', 'seo-legibilidade' ), array( __CLASS__, 'render_meta_box' ), self::post_types(), 'normal', 'high' );
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( self::NONCE, 'seol_nonce' );
		$v = static function ( $field ) use ( $post ) {
			return Meta::post( $post->ID, $field );
		};
		$placeholder = Settings::get( 'title_template_singular' );
		?>
		<div class="seol" data-post-id="<?php echo (int) $post->ID; ?>">
			<div class="seol-scores">
				<span class="seol-badge" data-score="seo"><span class="seol-dot"></span><?php esc_html_e( 'SEO', 'seo-legibilidade' ); ?>: <strong>–</strong></span>
				<span class="seol-badge" data-score="readability"><span class="seol-dot"></span><?php esc_html_e( 'Legibilidade', 'seo-legibilidade' ); ?>: <strong>–</strong></span>
				<span class="seol-badge" data-score="quality"><span class="seol-dot"></span><?php esc_html_e( 'Atualidade e confiança', 'seo-legibilidade' ); ?>: <strong>–</strong></span>
				<span class="seol-stats"></span>
				<button type="button" class="button button-primary seol-fix-all"><?php esc_html_e( 'Corrigir automaticamente', 'seo-legibilidade' ); ?></button>
			</div>

			<nav class="seol-tabs" role="tablist">
				<button type="button" role="tab" class="is-active" data-tab="seo"><?php esc_html_e( 'SEO', 'seo-legibilidade' ); ?></button>
				<button type="button" role="tab" data-tab="readability"><?php esc_html_e( 'Legibilidade', 'seo-legibilidade' ); ?></button>
				<button type="button" role="tab" data-tab="quality"><?php esc_html_e( 'Atualidade e confiança', 'seo-legibilidade' ); ?></button>
				<button type="button" role="tab" data-tab="social"><?php esc_html_e( 'Redes sociais', 'seo-legibilidade' ); ?></button>
				<button type="button" role="tab" data-tab="advanced"><?php esc_html_e( 'Avançado', 'seo-legibilidade' ); ?></button>
			</nav>

			<section class="seol-panel is-active" data-panel="seo">
				<p>
					<label for="seol_keyphrase"><strong><?php esc_html_e( 'Frase-chave de foco', 'seo-legibilidade' ); ?></strong></label>
					<input type="text" class="widefat" id="seol_keyphrase" name="seol[keyphrase]" value="<?php echo esc_attr( $v( 'keyphrase' ) ); ?>" placeholder="<?php esc_attr_e( 'Ex.: café especial', 'seo-legibilidade' ); ?>" />
				</p>

				<div class="seol-snippet" aria-label="<?php esc_attr_e( 'Prévia no Google', 'seo-legibilidade' ); ?>">
					<div class="seol-snippet-site"><span class="seol-snippet-favicon"></span><span><span class="seol-snippet-name"></span><span class="seol-snippet-url"></span></span></div>
					<div class="seol-snippet-title"></div>
					<div class="seol-snippet-desc"></div>
				</div>

				<p>
					<label for="seol_title"><strong><?php esc_html_e( 'Título SEO', 'seo-legibilidade' ); ?></strong></label>
					<input type="text" class="widefat" id="seol_title" name="seol[title]" value="<?php echo esc_attr( $v( 'title' ) ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" />
					<span class="seol-meter" data-meter="title"><span></span></span>
					<span class="description"><?php esc_html_e( 'Variáveis: %%title%%, %%sitename%%, %%sep%%, %%category%%, %%currentyear%%. Ideal: até ~580px (cerca de 60 caracteres).', 'seo-legibilidade' ); ?></span>
				</p>
				<p>
					<label for="seol_description"><strong><?php esc_html_e( 'Meta descrição', 'seo-legibilidade' ); ?></strong></label>
					<textarea class="widefat" rows="3" id="seol_description" name="seol[description]"><?php echo esc_textarea( $v( 'description' ) ); ?></textarea>
					<span class="seol-meter" data-meter="description"><span></span></span>
					<span class="description"><span class="seol-desc-count">0</span> <?php esc_html_e( 'caracteres. Ideal: 120 a 156.', 'seo-legibilidade' ); ?></span>
				</p>
				<input type="hidden" id="seol_slug" name="seol_slug" value="" />

				<h4><?php esc_html_e( 'Análise de SEO', 'seo-legibilidade' ); ?></h4>
				<ul class="seol-results" data-results="seo"><li class="seol-loading"><?php esc_html_e( 'Analisando…', 'seo-legibilidade' ); ?></li></ul>
			</section>

			<section class="seol-panel" data-panel="readability" hidden>
				<ul class="seol-results" data-results="readability"><li class="seol-loading"><?php esc_html_e( 'Analisando…', 'seo-legibilidade' ); ?></li></ul>
			</section>

			<section class="seol-panel" data-panel="quality" hidden>
				<p class="description"><?php esc_html_e( 'Sinais que as atualizações recentes do Google valorizam: informação atual, experiência própria, fontes, autoria e respostas diretas (trechos em destaque e AI Overviews).', 'seo-legibilidade' ); ?></p>
				<ul class="seol-results" data-results="quality"><li class="seol-loading"><?php esc_html_e( 'Analisando…', 'seo-legibilidade' ); ?></li></ul>
			</section>

			<section class="seol-panel" data-panel="social" hidden>
				<p class="description"><?php esc_html_e( 'Usados no Facebook, LinkedIn, WhatsApp e X. Se vazios, usamos o título SEO, a meta descrição e a imagem destacada. Imagem recomendada: 1200×630px.', 'seo-legibilidade' ); ?></p>
				<p>
					<label for="seol_og_title"><strong><?php esc_html_e( 'Título social', 'seo-legibilidade' ); ?></strong></label>
					<input type="text" class="widefat" id="seol_og_title" name="seol[og_title]" value="<?php echo esc_attr( $v( 'og_title' ) ); ?>" />
				</p>
				<p>
					<label for="seol_og_description"><strong><?php esc_html_e( 'Descrição social', 'seo-legibilidade' ); ?></strong></label>
					<textarea class="widefat" rows="2" id="seol_og_description" name="seol[og_description]"><?php echo esc_textarea( $v( 'og_description' ) ); ?></textarea>
				</p>
				<p>
					<label for="seol_og_image"><strong><?php esc_html_e( 'URL da imagem social', 'seo-legibilidade' ); ?></strong></label>
					<input type="url" class="widefat" id="seol_og_image" name="seol[og_image]" value="<?php echo esc_attr( $v( 'og_image' ) ); ?>" />
				</p>
			</section>

			<section class="seol-panel" data-panel="advanced" hidden>
				<p>
					<label><input type="checkbox" name="seol[noindex]" value="1" <?php checked( (bool) $v( 'noindex' ) ); ?> /> <?php esc_html_e( 'Não indexar (noindex) — remove do Google e do sitemap', 'seo-legibilidade' ); ?></label><br />
					<label><input type="checkbox" name="seol[nofollow]" value="1" <?php checked( (bool) $v( 'nofollow' ) ); ?> /> <?php esc_html_e( 'Não seguir links (nofollow)', 'seo-legibilidade' ); ?></label>
				</p>
				<p>
					<label for="seol_canonical"><strong><?php esc_html_e( 'URL canônica', 'seo-legibilidade' ); ?></strong></label>
					<input type="url" class="widefat" id="seol_canonical" name="seol[canonical]" value="<?php echo esc_attr( $v( 'canonical' ) ); ?>" placeholder="<?php echo esc_attr( get_permalink( $post ) ); ?>" />
					<span class="description"><?php esc_html_e( 'Deixe vazio para usar a própria URL. Preencha só se este conteúdo for cópia de outra página.', 'seo-legibilidade' ); ?></span>
				</p>
				<p>
					<label for="seol_schema_type"><strong><?php esc_html_e( 'Tipo de dados estruturados', 'seo-legibilidade' ); ?></strong></label>
					<select id="seol_schema_type" name="seol[schema_type]">
						<?php
						$types = array(
							''            => __( 'Padrão', 'seo-legibilidade' ),
							'Article'     => 'Article',
							'BlogPosting' => 'BlogPosting',
							'NewsArticle' => 'NewsArticle',
							'WebPage'     => 'WebPage',
							'AboutPage'   => __( 'AboutPage (Sobre)', 'seo-legibilidade' ),
							'ContactPage' => __( 'ContactPage (Contato)', 'seo-legibilidade' ),
							'ProfilePage' => __( 'ProfilePage (Perfil)', 'seo-legibilidade' ),
						);
						foreach ( $types as $value => $label ) {
							printf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( $v( 'schema_type' ), $value, false ), esc_html( $label ) );
						}
						?>
					</select>
				</p>
			</section>
		</div>
		<?php
	}

	public static function save_post( $post_id, $post ) {
		if ( ! isset( $_POST['seol_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['seol_nonce'] ), self::NONCE ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$input = isset( $_POST['seol'] ) && is_array( $_POST['seol'] ) ? wp_unslash( $_POST['seol'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		foreach ( Meta::POST_FIELDS as $field => $type ) {
			$value = Meta::sanitize( $field, isset( $input[ $field ] ) ? $input[ $field ] : '' );
			if ( '' === $value || false === $value ) {
				delete_post_meta( $post_id, Meta::key( $field ) );
			} else {
				update_post_meta( $post_id, Meta::key( $field ), $value );
			}
		}
		Analysis_Service::store_scores( $post_id );
	}

	/**
	 * Aplica o slug sugerido pela correção automática (editor clássico).
	 */
	public static function apply_slug( $data, $postarr ) {
		if ( empty( $_POST['seol_slug'] ) || ! isset( $_POST['seol_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['seol_nonce'] ), self::NONCE ) ) {
			return $data;
		}
		if ( empty( $postarr['ID'] ) || ! current_user_can( 'edit_post', $postarr['ID'] ) ) {
			return $data;
		}
		$slug = sanitize_title( wp_unslash( $_POST['seol_slug'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( '' !== $slug ) {
			$data['post_name'] = wp_unique_post_slug( $slug, $postarr['ID'], $data['post_status'], $data['post_type'], $data['post_parent'] );
		}
		return $data;
	}

	public static function assets( $hook ) {
		$screen = get_current_screen();
		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && $screen && in_array( $screen->post_type, self::post_types(), true ) ) {
			wp_enqueue_style( 'seol-admin', SEOL_URL . 'assets/css/admin.css', array(), SEOL_VERSION );
			wp_enqueue_script( 'seol-editor', SEOL_URL . 'assets/js/editor.js', array(), SEOL_VERSION, true );
			wp_localize_script(
				'seol-editor',
				'SEOL',
				array(
					'restUrl' => esc_url_raw( rest_url( Rest::NS ) ),
					'nonce'   => wp_create_nonce( 'wp_rest' ),
					'i18n'    => array(
						'good'        => __( 'Bom', 'seo-legibilidade' ),
						'ok'          => __( 'Pode melhorar', 'seo-legibilidade' ),
						'bad'         => __( 'Problema', 'seo-legibilidade' ),
						'fix'         => __( 'Corrigir', 'seo-legibilidade' ),
						'fixed'       => __( 'Correções aplicadas. Revise os campos e salve o post.', 'seo-legibilidade' ),
						'words'       => __( 'palavras', 'seo-legibilidade' ),
						'minutes'     => __( 'min de leitura', 'seo-legibilidade' ),
						'error'       => __( 'Não foi possível analisar agora.', 'seo-legibilidade' ),
						'autoDesc'    => __( 'gerada automaticamente', 'seo-legibilidade' ),
						'altClassic'  => __( 'No editor clássico, o texto alternativo das imagens sem alt é preenchido automaticamente na exibição do site (ative em Configurações).', 'seo-legibilidade' ),
					),
				)
			);
		}
		if ( 'settings_page_seo-legibilidade' === $hook ) {
			wp_enqueue_style( 'seol-admin', SEOL_URL . 'assets/css/admin.css', array(), SEOL_VERSION );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Colunas na listagem                                                 */
	/* ------------------------------------------------------------------ */

	public static function columns( $columns ) {
		$columns['seol'] = '<span title="' . esc_attr__( 'SEO / Legibilidade', 'seo-legibilidade' ) . '">' . esc_html__( 'SEO', 'seo-legibilidade' ) . '</span>';
		return $columns;
	}

	public static function column( $column, $post_id ) {
		if ( 'seol' !== $column ) {
			return;
		}
		foreach ( array( 'seo_score' => __( 'SEO', 'seo-legibilidade' ), 'readability_score' => __( 'Legibilidade', 'seo-legibilidade' ), 'quality_score' => __( 'Atualidade e confiança', 'seo-legibilidade' ) ) as $field => $label ) {
			$score = Meta::post( $post_id, $field );
			if ( '' === $score ) {
				printf( '<span class="seol-col" title="%s">–</span> ', esc_attr( $label ) );
				continue;
			}
			$rating = Analysis\Result::rating_for_score( (int) $score );
			$colors = array( 'good' => '#1e8a3a', 'ok' => '#d98500', 'bad' => '#d63638' );
			printf(
				'<span class="seol-col" title="%1$s: %2$d/100"><span style="display:inline-block;width:10px;height:10px;border-radius:50%%;background:%3$s"></span> %2$d</span> ',
				esc_attr( $label ),
				(int) $score,
				esc_attr( $colors[ $rating ] )
			);
		}
		if ( Meta::post( $post_id, 'noindex' ) ) {
			echo '<br /><small>noindex</small>';
		}
	}

	/* ------------------------------------------------------------------ */
	/* Termos (categorias, tags, taxonomias)                               */
	/* ------------------------------------------------------------------ */

	public static function term_hooks() {
		foreach ( get_taxonomies( array( 'public' => true ), 'names' ) as $tax ) {
			if ( 'post_format' === $tax ) {
				continue;
			}
			add_action( "{$tax}_edit_form_fields", array( __CLASS__, 'term_fields' ), 10, 1 );
			add_action( "edited_{$tax}", array( __CLASS__, 'save_term' ) );
		}
	}

	public static function term_fields( $term ) {
		wp_nonce_field( 'seol_term', 'seol_term_nonce' );
		?>
		<tr class="form-field"><th colspan="2"><h2><?php esc_html_e( 'SEO & Legibilidade', 'seo-legibilidade' ); ?></h2></th></tr>
		<tr class="form-field">
			<th scope="row"><label for="seol_term_title"><?php esc_html_e( 'Título SEO', 'seo-legibilidade' ); ?></label></th>
			<td><input type="text" id="seol_term_title" name="seol_term[title]" value="<?php echo esc_attr( Meta::term( $term->term_id, 'title' ) ); ?>" placeholder="<?php echo esc_attr( Settings::get( 'title_template_archive' ) ); ?>" /></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="seol_term_description"><?php esc_html_e( 'Meta descrição', 'seo-legibilidade' ); ?></label></th>
			<td><textarea id="seol_term_description" name="seol_term[description]" rows="3"><?php echo esc_textarea( Meta::term( $term->term_id, 'description' ) ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Entre 120 e 156 caracteres.', 'seo-legibilidade' ); ?></p></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Indexação', 'seo-legibilidade' ); ?></th>
			<td><label><input type="checkbox" name="seol_term[noindex]" value="1" <?php checked( (bool) Meta::term( $term->term_id, 'noindex' ) ); ?> /> <?php esc_html_e( 'Não indexar este arquivo (noindex)', 'seo-legibilidade' ); ?></label></td>
		</tr>
		<?php
	}

	public static function save_term( $term_id ) {
		if ( ! isset( $_POST['seol_term_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['seol_term_nonce'] ), 'seol_term' ) || ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}
		$input = isset( $_POST['seol_term'] ) && is_array( $_POST['seol_term'] ) ? wp_unslash( $_POST['seol_term'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		foreach ( Meta::TERM_FIELDS as $field => $type ) {
			$value = Meta::sanitize( $field, isset( $input[ $field ] ) ? $input[ $field ] : '', Meta::TERM_FIELDS );
			if ( '' === $value || false === $value ) {
				delete_term_meta( $term_id, Meta::key( $field ) );
			} else {
				update_term_meta( $term_id, Meta::key( $field ), $value );
			}
		}
	}

	/* ------------------------------------------------------------------ */
	/* Configurações                                                       */
	/* ------------------------------------------------------------------ */

	public static function menu() {
		add_options_page( __( 'SEO & Legibilidade', 'seo-legibilidade' ), __( 'SEO & Legibilidade', 'seo-legibilidade' ), 'manage_options', 'seo-legibilidade', array( __CLASS__, 'settings_page' ) );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=seo-legibilidade' ) ) . '">' . esc_html__( 'Configurações', 'seo-legibilidade' ) . '</a>' );
		return $links;
	}

	public static function register_settings() {
		register_setting(
			'seol',
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);
	}

	private static function field( $key, $label, $type = 'text', $help = '', $options = array() ) {
		$name  = Settings::OPTION . '[' . $key . ']';
		$value = Settings::get( $key );
		echo '<tr><th scope="row"><label for="seol-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		switch ( $type ) {
			case 'checkbox':
				printf( '<label><input type="checkbox" id="seol-%1$s" name="%2$s" value="1" %3$s /> %4$s</label>', esc_attr( $key ), esc_attr( $name ), checked( (bool) $value, true, false ), esc_html( $help ) );
				$help = '';
				break;
			case 'textarea':
				printf( '<textarea class="large-text" rows="3" id="seol-%1$s" name="%2$s">%3$s</textarea>', esc_attr( $key ), esc_attr( $name ), esc_textarea( $value ) );
				break;
			case 'select':
				printf( '<select id="seol-%1$s" name="%2$s">', esc_attr( $key ), esc_attr( $name ) );
				foreach ( $options as $opt => $opt_label ) {
					printf( '<option value="%s"%s>%s</option>', esc_attr( $opt ), selected( $value, $opt, false ), esc_html( $opt_label ) );
				}
				echo '</select>';
				break;
			default:
				printf( '<input type="%1$s" class="regular-text" id="seol-%2$s" name="%3$s" value="%4$s" />', esc_attr( $type ), esc_attr( $key ), esc_attr( $name ), esc_attr( $value ) );
		}
		if ( $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}

	public static function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap seol-settings">
			<h1><?php esc_html_e( 'SEO & Legibilidade', 'seo-legibilidade' ); ?></h1>
			<?php if ( ! get_option( 'blog_public' ) ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'Atenção: o site está configurado para desencorajar mecanismos de busca (Configurações → Leitura). Nada será indexado.', 'seo-legibilidade' ); ?></p></div>
			<?php endif; ?>
			<?php if ( false === strpos( (string) get_option( 'permalink_structure' ), '%postname%' ) ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'Recomendado: use links permanentes com o nome do post (Configurações → Links permanentes → “Nome do post”).', 'seo-legibilidade' ); ?></p></div>
			<?php endif; ?>
			<?php if ( ! current_theme_supports( 'title-tag' ) ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'Seu tema não declara suporte a “title-tag”; os títulos SEO podem não ser aplicados.', 'seo-legibilidade' ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'seol' ); ?>

				<h2><?php esc_html_e( 'Títulos e descrições', 'seo-legibilidade' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					self::field( 'separator', __( 'Separador de título', 'seo-legibilidade' ), 'select', '', array( '-' => '-', '–' => '–', '|' => '|', '·' => '·', '•' => '•', '»' => '»' ) );
					self::field( 'title_template_singular', __( 'Modelo: posts e páginas', 'seo-legibilidade' ), 'text', __( 'Variáveis: %%title%%, %%sitename%%, %%sitedesc%%, %%sep%%, %%category%%, %%page%%, %%currentyear%%.', 'seo-legibilidade' ) );
					self::field( 'title_template_home', __( 'Modelo: página inicial', 'seo-legibilidade' ) );
					self::field( 'title_template_archive', __( 'Modelo: arquivos (categorias, tags, autores)', 'seo-legibilidade' ), 'text', __( 'Use %%term%% para o nome do arquivo.', 'seo-legibilidade' ) );
					self::field( 'home_description', __( 'Meta descrição da página inicial', 'seo-legibilidade' ), 'textarea', __( 'Entre 120 e 156 caracteres.', 'seo-legibilidade' ) );
					self::field( 'auto_description', __( 'Descrição automática', 'seo-legibilidade' ), 'checkbox', __( 'Gerar meta descrição a partir do conteúdo quando ela estiver vazia', 'seo-legibilidade' ) );
					?>
				</table>

				<h2><?php esc_html_e( 'Identidade e dados estruturados', 'seo-legibilidade' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					self::field( 'entity_type', __( 'O site representa', 'seo-legibilidade' ), 'select', '', array( 'Organization' => __( 'Uma organização/empresa', 'seo-legibilidade' ), 'Person' => __( 'Uma pessoa', 'seo-legibilidade' ) ) );
					self::field( 'entity_name', __( 'Nome', 'seo-legibilidade' ), 'text', __( 'Vazio = nome do site.', 'seo-legibilidade' ) );
					self::field( 'entity_logo', __( 'URL do logotipo', 'seo-legibilidade' ), 'url', __( 'Quadrado, mínimo 112×112px. Vazio = logotipo do tema.', 'seo-legibilidade' ) );
					self::field( 'same_as', __( 'Perfis oficiais', 'seo-legibilidade' ), 'textarea', __( 'Uma URL por linha (Instagram, LinkedIn, YouTube, Wikipedia…).', 'seo-legibilidade' ) );
					self::field( 'article_type', __( 'Tipo de schema dos posts', 'seo-legibilidade' ), 'select', '', array( 'BlogPosting' => 'BlogPosting', 'Article' => 'Article', 'NewsArticle' => 'NewsArticle' ) );
					?>
				</table>

				<h2><?php esc_html_e( 'Redes sociais', 'seo-legibilidade' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					self::field( 'default_og_image', __( 'Imagem social padrão', 'seo-legibilidade' ), 'url', __( 'Usada quando o conteúdo não tem imagem. Recomendado: 1200×630px.', 'seo-legibilidade' ) );
					self::field( 'twitter_site', __( 'Usuário no X (Twitter)', 'seo-legibilidade' ), 'text', __( 'Sem @.', 'seo-legibilidade' ) );
					?>
				</table>

				<h2><?php esc_html_e( 'Indexação', 'seo-legibilidade' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					self::field( 'noindex_date_archives', __( 'Arquivos por data', 'seo-legibilidade' ), 'checkbox', __( 'Não indexar (evita conteúdo duplicado)', 'seo-legibilidade' ) );
					self::field( 'noindex_author_archives', __( 'Arquivos de autor', 'seo-legibilidade' ), 'checkbox', __( 'Não indexar (recomendado para sites com um único autor)', 'seo-legibilidade' ) );
					self::field( 'redirect_attachments', __( 'Páginas de anexo', 'seo-legibilidade' ), 'checkbox', __( 'Redirecionar (301) para o post ou para o arquivo', 'seo-legibilidade' ) );
					self::field( 'show_updated_date', __( 'Data de atualização', 'seo-legibilidade' ), 'checkbox', __( 'Mostrar “Atualizado em …” no início dos posts revisados depois da publicação', 'seo-legibilidade' ) );
					self::field( 'auto_alt', __( 'Texto alternativo', 'seo-legibilidade' ), 'checkbox', __( 'Preencher automaticamente o alt de imagens sem descrição (usa alt da biblioteca, legenda, título ou nome do arquivo)', 'seo-legibilidade' ) );
					?>
				</table>

				<h2><?php esc_html_e( 'Análise e verificação', 'seo-legibilidade' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					self::field( 'language', __( 'Idioma da análise', 'seo-legibilidade' ), 'select', '', array( 'auto' => __( 'Automático (idioma do site)', 'seo-legibilidade' ), 'pt' => __( 'Português', 'seo-legibilidade' ), 'en' => __( 'Inglês', 'seo-legibilidade' ) ) );
					self::field( 'min_words', __( 'Mínimo de palavras', 'seo-legibilidade' ), 'number', __( 'Padrão: 300.', 'seo-legibilidade' ) );
					self::field( 'google_verification', __( 'Google Search Console', 'seo-legibilidade' ), 'text', __( 'Código de verificação (ou cole a meta tag inteira).', 'seo-legibilidade' ) );
					self::field( 'bing_verification', __( 'Bing Webmaster Tools', 'seo-legibilidade' ), 'text', __( 'Código de verificação (ou cole a meta tag inteira).', 'seo-legibilidade' ) );
					?>
				</table>
				<?php submit_button(); ?>
			</form>
			<p>
				<?php
				printf(
					/* translators: %s: URL do sitemap. */
					esc_html__( 'Sitemap XML: %s — envie-o ao Google Search Console.', 'seo-legibilidade' ),
					'<a href="' . esc_url( home_url( '/wp-sitemap.xml' ) ) . '" target="_blank" rel="noopener">' . esc_html( home_url( '/wp-sitemap.xml' ) ) . '</a>'
				);
				?>
			</p>
		</div>
		<?php
	}
}
