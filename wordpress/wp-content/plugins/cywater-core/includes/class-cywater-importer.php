<?php
/**
 * Idempotent importer from the verified static content registry.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Importer {
	private $data;
	private $image_cache = array();
	private $force;
	private $syncable_posts = array();

	public function __construct( $data_file = '', $force = false ) {
		$data_file = $data_file ?: CYWATER_CORE_DIR . 'data/seed.json';
		$this->force = (bool) $force;
		if ( ! is_readable( $data_file ) ) {
			throw new RuntimeException( 'CYWater seed data is missing or unreadable.' );
		}
		$this->data = json_decode( file_get_contents( $data_file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_array( $this->data ) || 1 !== (int) ( $this->data['schemaVersion'] ?? 0 ) ) {
			throw new RuntimeException( 'CYWater seed data has an unsupported schema.' );
		}
	}

	public function run() {
		$this->ensure_terms();
		$report = array(
			'pages'  => $this->import_pages(),
			'news'   => $this->import_news(),
			'events' => $this->import_events(),
			'awards' => $this->import_awards(),
			'board'  => $this->import_board_roles(),
		);
		return $report;
	}

	private function ensure_terms() {
		foreach ( array( 'meeting' => 'Annual Meeting', 'gathering' => 'Annual Gathering' ) as $slug => $name ) {
			if ( ! term_exists( $slug, 'cyw_event_type' ) ) {
				wp_insert_term( $name, 'cyw_event_type', array( 'slug' => $slug ) );
			}
		}
	}

	private function import_pages() {
		$verified_pages = $this->data['pages'] ?? array();
		$pages = array(
			'home'       => array( 'Home', 'International Association · Water Sciences', 'Advancing water sciences, empowering young scholars.', '' ),
			'about'      => array( 'About CYWater', 'Our association', 'Advancing water sciences for the public benefit.', '<p>CYWater is an international non-profit association founded in 2011 to advance education, research, and professional development in water sciences.</p>' ),
			'board'      => array( 'Board of Directors', 'Leadership', 'The Board governs CYWater, sets strategic direction, oversees finances, appoints committees, and ensures compliance with law and mission.', '<div class="callout"><strong>Leadership update in progress.</strong><p>Current appointments will be published only after formal review.</p></div>' ),
			'bylaws'     => array( 'Bylaws', 'Governance', 'The governing framework of the association.', '<p>The approved Bylaws document and article-by-article text will be migrated during editorial acceptance.</p>' ),
			'membership' => array( 'Membership', 'Join CYWater', 'Join an international community advancing water sciences and supporting emerging scholars.', '<p>Membership is open worldwide to individuals professionally engaged in or interested in water sciences, water resources, or related disciplines.</p>' ),
			'news'       => array( 'News', 'News and updates', 'Opportunities and spotlights.', '' ),
			'contact'    => array( 'Get in touch', 'Contact', 'CYWater\'s official contact email is being confirmed.', '<h2>Official channels are being verified.</h2><p>Membership, event, partnership, and media inquiries will be accepted once the official contact channel is confirmed.</p>' ),
			'account'    => array( 'Member account', 'Membership', 'Manage your CYWater membership and profile.', '[pmpro_account]' ),
			'member-profile' => array( 'Member profile', 'Membership profile', 'Complete your professional profile and choose what may appear publicly.', '[pmpro_member_profile_edit][cywater_privacy_settings]' ),
		);
		foreach ( array( 'about', 'bylaws', 'membership', 'contact' ) as $slug ) {
			if ( empty( $verified_pages[ $slug ] ) ) {
				continue;
			}
			$page = $verified_pages[ $slug ];
			$pages[ $slug ][0] = sanitize_text_field( $page['title'] ?? $pages[ $slug ][0] );
			$pages[ $slug ][1] = sanitize_text_field( $page['eyebrow'] ?? $pages[ $slug ][1] );
			$pages[ $slug ][2] = sanitize_text_field( $page['lead'] ?? $pages[ $slug ][2] );
			if ( in_array( $slug, array( 'about', 'bylaws' ), true ) && ! empty( $page['content'] ) ) {
				$pages[ $slug ][3] = wp_kses_post( $page['content'] );
			}
		}

		$result = array();
		foreach ( $pages as $slug => $page ) {
			$post_id = $this->upsert_post(
				'page',
				'page:' . $slug,
				array(
					'post_title'   => $page[0],
					'post_name'    => $slug,
					'post_excerpt' => $page[2],
					'post_content' => $page[3],
					'post_status'  => 'publish',
				)
			);
			$this->seed_meta( $post_id, '_cyw_eyebrow', $page[1] );
			$this->seed_meta( $post_id, '_cyw_lead', $page[2] );
			if ( 'contact' === $slug ) {
				$this->seed_meta( $post_id, '_cyw_contact_email', '' );
				$this->seed_meta( $post_id, '_cyw_mailing_address', "202 E. Green St. Suite 2\nChampaign, IL 61820, USA" );
			}
			$result[ $slug ] = $post_id;
		}
		return $result;
	}

	private function import_news() {
		$visuals = array();
		foreach ( $this->data['newsOrder'] ?? array() as $item ) {
			$visuals[ $item['id'] ] = $item;
		}
		$count = 0;
		foreach ( $this->data['articles'] as $source_id => $article ) {
			$post_id = $this->upsert_post(
				'post',
				'news:' . $source_id,
				array(
					'post_title'   => $article['title'],
					'post_name'    => sanitize_title( $source_id ),
					'post_excerpt' => $article['lead'] ?? '',
					'post_content' => $this->render_blocks( $article['blocks'] ?? array() ),
					'post_date'    => $this->wordpress_date( $article['date'] ?? '' ),
					'post_status'  => 'publish',
				)
			);
			$category = $this->ensure_category( sanitize_text_field( $article['tag'] ?? 'News' ) );
			if ( $this->should_sync( $post_id ) ) {
				wp_set_post_categories( $post_id, $category ? array( $category ) : array() );
			}
			$this->seed_meta( $post_id, '_cyw_source_url', esc_url_raw( $article['source'] ?? '' ) );
			$display = $visuals[ $source_id ] ?? array();
			if ( ! empty( $display['visual'] ) ) {
				$this->seed_meta( $post_id, '_cyw_visual_title', sanitize_text_field( $display['visual']['title'] ?? '' ) );
				$this->seed_meta( $post_id, '_cyw_visual_year', absint( $display['visual']['year'] ?? 0 ) );
			}
			if ( ! empty( $display['img'] ) ) {
				$this->set_featured_image( $post_id, $display['img'], $display['alt'] ?? $article['title'] );
			}
			++$count;
		}
		return $count;
	}

	private function import_events() {
		$ordering = array();
		foreach ( $this->data['eventOrder'] ?? array() as $item ) {
			$ordering[ $item['id'] ] = $item;
		}
		$count = 0;
		foreach ( $this->data['events'] as $source_id => $event ) {
			$display_date = sanitize_text_field( $event['date'] ?? '' );
			$start_date   = $this->infer_event_date( $source_id, $display_date );
			$post_id      = $this->upsert_post(
				'cyw_event',
				'event:' . $source_id,
				array(
					'post_title'   => $event['title'],
					'post_name'    => sanitize_title( $source_id ),
					'post_excerpt' => $event['lead'] ?? '',
					'post_content' => $this->render_blocks( $event['blocks'] ?? array() ),
					'post_date'    => $this->wordpress_date( $start_date ),
					'post_status'  => 'publish',
				)
			);
			$type   = $event['category'] ?? ( $ordering[ $source_id ]['category'] ?? 'meeting' );
			$status = ! empty( $event['upcoming'] ) || 'upcoming' === ( $ordering[ $source_id ]['status'] ?? '' ) ? 'upcoming' : 'past';
			if ( $this->should_sync( $post_id ) ) {
				wp_set_object_terms( $post_id, $type, 'cyw_event_type' );
			}
			$this->seed_meta( $post_id, '_cyw_start_date', $start_date );
			$this->seed_meta( $post_id, '_cyw_date_label', $display_date );
			$this->seed_meta( $post_id, '_cyw_location', sanitize_text_field( $event['location'] ?? '' ) );
			$this->seed_meta( $post_id, '_cyw_format', sanitize_text_field( $event['format'] ?? '' ) );
			$this->seed_meta( $post_id, '_cyw_attendees', sanitize_text_field( $event['attendees'] ?? '' ) );
			$this->seed_meta( $post_id, '_cyw_status', $status );
			if ( ! empty( $event['image'] ) ) {
				$this->set_featured_image( $post_id, $event['image'], $event['imageAlt'] ?? $event['title'] );
			}
			++$count;
		}
		return $count;
	}

	private function import_awards() {
		$count = 0;
		foreach ( $this->data['awards'] as $award ) {
			$year       = absint( $award['year'] ?? 0 );
			$best       = $award['bestPaper'] ?? null;
			$recipient  = $best['author'] ?? '';
			$paper      = $best['title'] ?? '';
			$journal    = $best['journal'] ?? '';
			$content    = '';
			if ( $best ) {
				$content .= '<h2>Best Paper</h2><p><strong>' . esc_html( $recipient ) . '</strong></p><p>' . esc_html( $paper ) . ( $journal ? '<br><em>' . esc_html( $journal ) . '</em>' : '' ) . '</p>';
			}
			if ( ! empty( $award['outstanding'] ) ) {
				$content .= '<h2>Outstanding Papers</h2><ul>';
				foreach ( $award['outstanding'] as $outstanding ) {
					$content .= '<li><strong>' . esc_html( $outstanding['author'] ?? '' ) . '</strong> — ' . esc_html( $outstanding['title'] ?? '' ) . '</li>';
				}
				$content .= '</ul>';
			}
			if ( ! empty( $award['note'] ) ) {
				$content .= '<p>' . esc_html( $award['note'] ) . '</p>';
			}
			$post_id = $this->upsert_post(
				'cyw_award',
				'award:' . $year,
				array(
					'post_title'   => 'CYWater Young Scientist Best Paper Award ' . $year,
					'post_name'    => 'best-paper-award-' . $year,
					'post_excerpt' => $best ? $recipient . ' — ' . $paper : ( $award['note'] ?? '' ),
					'post_content' => $content,
					'post_date'    => $year . '-12-01 12:00:00',
					'post_status'  => 'publish',
				)
			);
			$this->seed_meta( $post_id, '_cyw_year', $year );
			$this->seed_meta( $post_id, '_cyw_recipient', sanitize_text_field( $recipient ) );
			$this->seed_meta( $post_id, '_cyw_paper_title', sanitize_text_field( $paper ) );
			$this->seed_meta( $post_id, '_cyw_journal', sanitize_text_field( $journal ) );
			$this->seed_meta( $post_id, '_cyw_applications', absint( $award['applications'] ?? 0 ) );
			$this->seed_meta( $post_id, '_cyw_chair', sanitize_text_field( $award['chair'] ?? '' ) );
			++$count;
		}
		return $count;
	}

	private function import_board_roles() {
		$roles = array( 'President', 'President-Elect', 'Treasurer', 'Directors-at-Large', 'Executive Director' );
		foreach ( $roles as $index => $role ) {
			$post_id = $this->upsert_post( 'cyw_board_role', 'board:' . sanitize_title( $role ), array( 'post_title' => $role, 'post_status' => 'publish' ) );
			$this->seed_meta( $post_id, '_cyw_order', $index + 1 );
			if ( '' === get_post_meta( $post_id, '_cyw_confirmed_public', true ) ) {
				update_post_meta( $post_id, '_cyw_confirmed_public', 0 );
			}
		}
		return count( $roles );
	}

	private function upsert_post( $post_type, $source_id, $post_data ) {
		$existing = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_cyw_source_id',
				'meta_value'     => $source_id,
			)
		);
		$post_data['post_type'] = $post_type;
		if ( $existing ) {
			$post_id = (int) $existing[0];
			$this->syncable_posts[ $post_id ] = $this->force;
			if ( $this->force ) {
				$post_data['ID'] = $post_id;
				$post_id         = wp_update_post( wp_slash( $post_data ), true );
			}
		} else {
			$post_id = wp_insert_post( wp_slash( $post_data ), true );
			if ( ! is_wp_error( $post_id ) ) {
				$this->syncable_posts[ (int) $post_id ] = true;
			}
		}
		if ( is_wp_error( $post_id ) ) {
			throw new RuntimeException( $post_id->get_error_message() );
		}
		update_post_meta( $post_id, '_cyw_source_id', $source_id );
		return $post_id;
	}

	private function should_sync( $post_id ) {
		return ! empty( $this->syncable_posts[ (int) $post_id ] );
	}

	private function ensure_category( $name ) {
		$term = term_exists( $name, 'category' );
		if ( ! $term ) {
			$term = wp_insert_term( $name, 'category' );
		}
		if ( is_wp_error( $term ) ) {
			return 0;
		}
		return (int) ( is_array( $term ) ? $term['term_id'] : $term );
	}

	private function seed_meta( $post_id, $key, $value ) {
		if ( $this->should_sync( $post_id ) ) {
			update_post_meta( $post_id, $key, $value );
		}
	}

	private function render_blocks( $blocks ) {
		$html = '';
		foreach ( $blocks as $block ) {
			$type = $block['type'] ?? '';
			if ( in_array( $type, array( 'p', 'h2', 'h3' ), true ) ) {
				$html .= '<' . $type . '>' . esc_html( $block['text'] ?? '' ) . '</' . $type . '>';
			} elseif ( 'quote' === $type ) {
				$html .= '<blockquote>' . esc_html( $block['text'] ?? '' ) . '</blockquote>';
			} elseif ( 'list' === $type ) {
				$html .= '<ul>';
				foreach ( $block['items'] ?? array() as $item ) {
					$html .= '<li>' . esc_html( $item ) . '</li>';
				}
				$html .= '</ul>';
			} elseif ( 'figure' === $type ) {
				$html .= $this->figure_html( $block );
			} elseif ( 'gallery' === $type ) {
				$html .= '<div class="gallery-grid">';
				foreach ( $block['items'] ?? array() as $item ) {
					$html .= $this->figure_html( $item );
				}
				$html .= '</div>';
			} elseif ( 'table' === $type ) {
				$html .= '<div class="table-wrap"><table class="table"><thead><tr>';
				foreach ( $block['head'] ?? array() as $cell ) {
					$html .= '<th scope="col">' . esc_html( $cell ) . '</th>';
				}
				$html .= '</tr></thead><tbody>';
				foreach ( $block['rows'] ?? array() as $row ) {
					$html .= '<tr>';
					foreach ( $row as $cell ) {
						$html .= '<td>' . esc_html( $cell ) . '</td>';
					}
					$html .= '</tr>';
				}
				$html .= '</tbody></table></div>';
			}
		}
		return $html;
	}

	private function figure_html( $item ) {
		$attachment_id = $this->import_image( $item['src'] ?? '', $item['caption'] ?? '' );
		if ( ! $attachment_id ) {
			return '';
		}
		return '<figure>' . wp_get_attachment_image( $attachment_id, 'large', false, array( 'loading' => 'lazy' ) ) . ( ! empty( $item['caption'] ) ? '<figcaption>' . esc_html( $item['caption'] ) . '</figcaption>' : '' ) . '</figure>';
	}

	private function set_featured_image( $post_id, $relative_path, $alt ) {
		if ( ! $this->should_sync( $post_id ) ) {
			return;
		}
		$attachment_id = $this->import_image( $relative_path, $alt );
		if ( $attachment_id ) {
			set_post_thumbnail( $post_id, $attachment_id );
		} else {
			update_post_meta( $post_id, '_cyw_source_image', sanitize_text_field( $relative_path ) );
		}
	}

	private function import_image( $relative_path, $alt = '' ) {
		$relative_path = ltrim( sanitize_text_field( $relative_path ), '/' );
		if ( ! $relative_path ) {
			return 0;
		}
		if ( isset( $this->image_cache[ $relative_path ] ) ) {
			return $this->image_cache[ $relative_path ];
		}
		$existing = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_cyw_source_image', 'meta_value' => $relative_path ) );
		if ( $existing ) {
			return $this->image_cache[ $relative_path ] = $existing[0];
		}
		$source = get_theme_file_path( 'assets/img/' . $relative_path );
		if ( ! is_readable( $source ) ) {
			return 0;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$tmp = wp_tempnam( basename( $source ) );
		if ( ! $tmp || ! copy( $source, $tmp ) ) {
			return 0;
		}
		$file = array( 'name' => basename( $source ), 'tmp_name' => $tmp );
		$id   = media_handle_sideload( $file, 0, sanitize_text_field( $alt ) );
		if ( is_wp_error( $id ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return 0;
		}
		update_post_meta( $id, '_cyw_source_image', $relative_path );
		update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
		return $this->image_cache[ $relative_path ] = $id;
	}

	private function infer_event_date( $source_id, $label ) {
		$known = array(
			'annual-2026'           => '2026-10-16',
			'annual-gathering-2024' => '2024-12-10',
			'annual-gathering-2022' => '2022-12-14',
			'annual-gathering-2020' => '2020-12-18',
		);
		if ( isset( $known[ $source_id ] ) ) {
			return $known[ $source_id ];
		}
		if ( preg_match( '/(20\d{2})/', $label . ' ' . $source_id, $matches ) ) {
			return $matches[1] . '-01-01';
		}
		return '2011-01-01';
	}

	private function wordpress_date( $date ) {
		$timestamp = strtotime( $date );
		return $timestamp ? gmdate( 'Y-m-d 12:00:00', $timestamp ) : current_time( 'mysql' );
	}
}
