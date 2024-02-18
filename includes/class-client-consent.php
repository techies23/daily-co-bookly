<?php

/**
 * Class for Shortcodes
 */
class Headroom_ClientConsent {

	private $file_path;

	public function __construct() {
		add_shortcode( 'headroom_consent_form', [ $this, 'output_form' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ] );

		add_action( 'wp_ajax_save_consent_form', [ $this, 'save_consent_form' ] );
		add_action( 'wp_ajax_admin_flush_consent', [ $this, 'flush_consent_form_backend' ] );

		add_action( 'template_redirect', [ $this, 'check_consent_users' ] );

		$this->file_path = $this->get_uploads_dir();
	}

	public function scripts() {
		wp_register_script( 'headroom-consent-script', DPEN_DAILY_CO_URI_PATH . 'assets/js/signature_pad.umd.js', array( 'jquery' ), '1.0.0', true );
		wp_register_script( 'headroom-consent', DPEN_DAILY_CO_URI_PATH . 'assets/js/consent.js', array( 'jquery' ), time(), true );
	}

	/**
	 * Flush consent form from backend
	 */
	public function flush_consent_form_backend() {
		$therapist_id = absint( filter_input( INPUT_POST, 'therapist' ) );
		$user_id      = absint( filter_input( INPUT_POST, 'user' ) );
		if ( ! empty( $therapist_id ) ) {
			//REST CONSENT META
			update_user_meta( $user_id, '_headroom_consent_approved_' . absint( $therapist_id ), '' );
			wp_send_json_success( 'Flushed user Consent form.' );
		}
		wp_die();
	}

	/**
	 * Check Consent Form Redirect Logic
	 */
	public function check_consent_users() {
		if ( ( is_cart() || is_checkout() ) && is_user_logged_in() ) {
			$current_userid             = get_current_user_id();
			$staffs                     = array();
			$consent_required_staff_ids = array();
			foreach ( WC()->cart->get_cart() as $wc_key => $wc_item ) {
				if ( array_key_exists( 'bookly', $wc_item ) ) {
					if ( ! empty( $wc_item['bookly']['items'] ) ) {
						foreach ( $wc_item['bookly']['items'] as $wc_cart_item ) {
							if ( ! empty( $wc_cart_item['staff_ids'][0] ) ) {
								$query = Daily_Co_Bookly_Datastore::getStaffUserIdBy_booklyUserId( $wc_cart_item['staff_ids'][0] );
								if ( ! empty( $query ) ) {
									$staffs[] = array(
										'id'         => $query->getId(),
										'full_name'  => $query->getFullName(),
										'wp_user_id' => $query->getWpUserId(),
										'email'      => $query->getEmail(),
										'phone'      => $query->getPhone(),
									);
								}
							}
						}
					}
				}
			}

			if ( ! empty( $staffs ) ) {
				$sorting_distinct_staffs = array_unique( $staffs, SORT_REGULAR );
				foreach ( $sorting_distinct_staffs as $sorting_distinct_staff ) {
					if ( ! empty( $sorting_distinct_staff['wp_user_id'] ) ) {
						$already_involved = self::check_consent( $current_userid, $sorting_distinct_staff['wp_user_id'] );
						if ( ! $already_involved ) {
							$consent_required_staff_ids[] = $sorting_distinct_staff['wp_user_id'];
						}
					}
				}
			}

			if ( ! empty( $consent_required_staff_ids ) ) {
				update_user_meta( $current_userid, '_headroom_consent_required_staff_ids', $consent_required_staff_ids );
				wp_redirect( esc_url( home_url( 'client-informed-consent-form?process=consent' ) ) );
				exit;
			}
		}

		#dump(get_user_meta( get_current_user_id(), '_headroom_consent_required_staff_ids', true ));

		//For reset purpose
		if ( is_page_template( 'templates/template-bookly-book.php' ) && is_user_logged_in() ) {
			$therapist_id = isset( $_GET['id'] ) ? $_GET['id'] : false;
			if ( ! empty( $therapist_id ) ) {
				$staff = Daily_Co_Bookly_Datastore::getStaffbyUserID( $therapist_id );
				if ( empty( $staff['wp_user_id'] ) ) {
					return;
				}

				//REST CONSENT META
				if ( isset( $_GET['reset'] ) && $_GET['reset'] == "flush_consent" ) {
					update_user_meta( get_current_user_id(), '_headroom_consent_approved_' . absint( $therapist_id ), '' );
					wp_redirect( esc_url( home_url( '/book-therapist/?id=' . $therapist_id ) ) );
					exit;
				}

				/*$already_involved = self::check_consent( get_current_user_id(), $staff['wp_user_id'] );
				if ( ! $already_involved ) {
					wp_redirect( esc_url( home_url( 'client-informed-consent-form?id=' . $therapist_id ) ) );
					exit;
				}*/
			}
		}
	}

	/**
	 * Check if user has already filled the consent form or not
	 *
	 * @param $client_id
	 * @param $therapist_id
	 *
	 * @return bool
	 */
	public static function check_consent( $client_id, $therapist_id ) {
		$consent_form_exists = get_user_meta( absint( $client_id ), '_headroom_consent_approved_' . absint( $therapist_id ), true );
		if ( ! empty( $consent_form_exists ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Save Consent Data
	 */
	public function save_consent_form() {
		$therapist_ids = filter_input( INPUT_POST, 'therapist_id' );
		$name          = filter_input( INPUT_POST, 'name' );
		$email         = filter_input( INPUT_POST, 'email' );
		$img           = filter_input( INPUT_POST, 'img' );
		$date_of_birth = filter_input( INPUT_POST, 'dob' );
		$date          = date( 'm/d/Y' ); //Todays Date

		if ( ! empty( $therapist_ids ) ) {
			$current_user_id = get_current_user_id();
			$therapist_ids   = explode( ',', $therapist_ids );
			if ( ! empty( $therapist_ids ) ) {
				foreach ( $therapist_ids as $therapist_id ) {
					$store = array(
						'therapist_id' => $therapist_id,
						'name'         => $name,
						'email'        => $email,
						'signature'    => $img,
						'dob'          => $date_of_birth,
						'age'          => $this->ageCalculator( $date_of_birth ),
						'date'         => $date
					);

					$file_path = $this->file_path . "consent-" . strtolower( $therapist_id ) . ".png";
					if ( ! empty( $img ) ) {
						$img  = str_replace( 'data:image/png;base64,', '', $img );
						$img  = str_replace( ' ', '+', $img );
						$data = base64_decode( $img );
						file_put_contents( $file_path, $data );
					}

					update_user_meta( $current_user_id, '_headroom_consent_approved_' . $therapist_id, $store );
					update_user_meta( $current_user_id, '_headroom_consent_required_staff_ids', '' );
					$practise_no = get_user_meta( $therapist_id, 'practice_no', true );

					$staff                 = Daily_Co_Bookly_Datastore::getStaffbyUserID( $therapist_id );
					$staff['client']       = $name;
					$staff['client_email'] = $email;
					if ( ! empty( $staff ) ) {
						if ( file_exists( $file_path ) ) {
							$signature = $file_path;
						} else {
							$signature = false;
						}

						$store['therapist_name'] = $staff['full_name'];
						$store['practice_no']    = $practise_no;
						$this->generate_pdf( $signature, $store );
						$this->send_email( $staff, $signature, $this->file_path . 'PDF_Client_Informed_Consent-' . $therapist_id . '.pdf' );
					}
				}

				wp_send_json_success( array(
					'msg'      => 'Submitted form. Please check your email for attachment. Redirecting you to your cart page...',
					'redirect' => wc_get_cart_url()
				) );
			}
		} else {
			wp_send_json_error( 'This from can only be submitted after selecting a therapist to book a session.' );
		}

		wp_die();
	}

	public function output_form() {
		ob_start();

		if ( is_user_logged_in() ) {
			$consent_form_valid = get_user_meta( get_current_user_id(), '_headroom_consent_required_staff_ids', true );
			if ( empty( $consent_form_valid ) ) {
				echo "<p>Please book a therapist first in order to proceed with consent form submission.";

				return false;
			}

			wp_enqueue_script( 'jquery-mask', DPEN_DAILY_CO_URI_PATH . 'assets/vendors/datepicker/jquery.datepickermask.min.js' );
			wp_enqueue_script( 'jquery-ui-datepicker' );
			wp_enqueue_style( 'jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/smoothness/jquery-ui.css' );
			wp_enqueue_script( 'headroom-consent-script' );
			wp_enqueue_script( 'headroom-consent' );

			$therapist_id     = isset( $_GET['id'] ) ? $_GET['id'] : false;
			$current_user_id  = get_current_user_id();
			$already_involved = self::check_consent( $current_user_id, $therapist_id );
			if ( $already_involved ) {
				return false;
			}

			if ( ! isset( $therapist_id ) || ! isset( $_GET['process'] ) ) {
				return false;
			}

			if ( isset( $_GET['process'] ) && $_GET['process'] === "consent" ) {
				$therapist_ids = get_user_meta( $current_user_id, '_headroom_consent_required_staff_ids', true );
				if ( ! empty( $therapist_ids ) ) {
					foreach ( $therapist_ids as $k => $therapist_id ) {
						$already_involved = self::check_consent( $current_user_id, $therapist_id );
						if ( $already_involved ) {
							unset( $therapist_ids[ $k ] );
						}
					}
				}
			}

			$user = get_userdata( $current_user_id );
			?>
            <div class="headroom-consent-warpper">
                <form action="" method="POST" id="headroom-consent-form">
					<?php if ( ! empty( $therapist_ids ) ) { ?>
                        <input type="hidden" name="therapist_id" value="<?php echo implode( ',', $therapist_ids ); ?>">
					<?php } ?>
                    <div class="hform-group">
                        <label for="headroom-consent-name">Your Name (Required)</label>
                        <input type="text" class="hform-control" id="headroom-consent-name" name="name" placeholder="Your Full Name Here" value="">
                    </div>
                    <div class="hform-group">
                        <label for="headroom-consent-email">Email (Required)</label>
                        <input type="email" value="<?php echo esc_html( $user->user_email ); ?>" class="hform-control" id="headroom-consent-email" name="email">
                    </div>
                    <div class="hform-group">
                        <p><strong>Please draw your signature by using your mouse or touch pad.</strong></p>
                        <label>Your signature (Required) or Signature of Guardian if you are <14 years old (Required)</label>
                        <div class="headroom-consent-signature-wrapper">
                            <canvas id="headroom-consent-signature-pad" class="headroom-consent-signature-pad" width=400 height=200></canvas>
                        </div>
                        <button id="headroom-clear-pad">Clear</button>
                    </div>
                    <div class="hform-group">
                        <label for="headroom-consent-date">Date of Birth (Required)</label>
                        <input type="text" placeholder="Your Date of Birth. (Correct date format should be DD/MM/YYYY)" class="hform-control" id="headroom-consent-dob" name="dob" value="">
                    </div>
                    <div class="hform-group">
                        <label for="headroom-consent-date">Date: </label>
						<?php echo date( 'F d, Y' ); ?>
                    </div>
                    <button type="submit" class="btn btn-primary consent-submit-btn" name="consent_form">Submit</button>
                </form>
            </div>
			<?php
		}

		return ob_get_clean();
	}

	/**
	 * Send Email Function
	 *
	 * @param $user_details
	 * @param $sig_temppath
	 * @param $doc_temppath
	 */
	public function send_email( $user_details, $sig_temppath, $doc_temppath ) {
		$from_email = esc_html( get_option( '_dpen_daily_from_email' ) );
		$from       = ! empty( $from_email ) ? $from_email : 'no-reply@headroom.co.za';

		//Ready for email
		$headers[]                = 'Content-Type: text/html; charset=UTF-8';
		$headers[]                = 'From: ' . get_bloginfo( 'name' ) . ' < ' . $from . ' >' . "\r\n";
		$email_template           = file_get_contents( DPEN_DAILY_CO_DIR_PATH . 'templates/emails/tpl-email-consent-approved.html' );
		$email_template_therpaist = file_get_contents( DPEN_DAILY_CO_DIR_PATH . 'templates/emails/tpl-email-consent-approved-therapist.html' );
		$email_template_admin     = file_get_contents( DPEN_DAILY_CO_DIR_PATH . 'templates/emails/tpl-email-consent-approved-admin.html' );

		$search_strings = array(
			'{site_title}',
			'{site_url}',
			'{year}',
			'{therapist_name}',
			'{therapist_fname}',
			'{client_name}',
			'{client_fname}'
		);

		$fname_therapist = explode( ' ', $user_details['full_name'] );
		$fname_client    = explode( ' ', $user_details['client'] );
		$replace_string  = array(
			get_bloginfo( 'name' ),
			home_url( '/' ),
			date( 'Y' ),
			$user_details['full_name'],
			$fname_therapist[0],
			$user_details['client'],
			$fname_client[0]
		);

		$body           = str_replace( $search_strings, $replace_string, $email_template );
		$body_therapist = str_replace( $search_strings, $replace_string, $email_template_therpaist );
		$body_admin     = str_replace( $search_strings, $replace_string, $email_template_admin );
		if ( $doc_temppath && file_exists( $doc_temppath ) ) {
			$attachments = array( $doc_temppath );
			wp_mail( $user_details['email'], 'Client Informed Consent', $body_therapist, $headers, $attachments );
			wp_mail( $user_details['client_email'], 'Client Informed Consent', $body, $headers, $attachments );
			wp_mail( get_bloginfo( 'admin_email' ), 'Client Informed Consent', $body_admin, $headers, $attachments );
			wp_delete_file( $doc_temppath );

			if ( file_exists( $sig_temppath ) ) {
				wp_delete_file( $sig_temppath );
			}
		} else {
			//Send to therapist
			wp_mail( $user_details['email'], 'Client Informed Consent', $body_therapist, $headers );

			//Send to admin
			wp_mail( get_bloginfo( 'admin_email' ), 'Client Informed Consent', $body_admin, $headers );

			//Send to User
			wp_mail( $user_details['client_email'], 'Client Informed Consent', $body, $headers );
		}
	}

	/**
	 * Generate PDF;
	 *
	 * @param $signature
	 * @param $data
	 */
	public function generate_pdf( $signature, $data ) {
		require_once DPEN_DAILY_CO_DIR_PATH . 'includes/fpdf/fpdf.php';
		define( 'FPDF_FONTPATH', DPEN_DAILY_CO_DIR_PATH . 'assets/font' );
		$pdf = new FPDF();
		$pdf->AddPage();
		$pdf->AddFont( 'Calibri', '', 'calibri.php' );
		$pdf->AddFont( 'Calibri', 'B', 'calibrib.php' );
		$pdf->SetFont( 'Calibri', 'B', 14 );
		$pdf->Cell( 0, 10, 'CLIENT INFORMED CONSENT FORM', 0, 0, 'C' );
		$pdf->Ln( 8 ); // Line gap
		$pdf->Cell( 0, 10, 'HEADROOM.CO.ZA', 0, 0, 'C' );
		$pdf->Ln( 20 ); // Line gap
		$pdf->SetFont( 'Calibri', '', 10 );
		$pdf->Cell( 60, 10, 'Name and Surname of Client', 1, 0, 'L', 0 );
		$pdf->Cell( 120, 10, ! empty( $data['name'] ) ? $data['name'] : '', 1, 0, 'L', 0 );
		$pdf->Ln();
		$pdf->Cell( 60, 10, 'Date of Birth', 1, 0, 'L', 0 );
		$pdf->Cell( 120, 10, ! empty( $data['dob'] ) ? $data['dob'] : '', 1, 0, 'L', 0 );
		$pdf->Ln();
		$pdf->Cell( 60, 10, 'Name and Surname of Therapist', 1, 0, 'L', 0 );
		$pdf->Cell( 120, 10, ! empty( $data['therapist_name'] ) ? $data['therapist_name'] : '', 1, 0, 'L', 0 );
		$pdf->Ln();
		$pdf->Cell( 60, 10, 'Therapist Practice No.', 1, 0, 'L', 0 );
		$pdf->Cell( 120, 10, ! empty( $data['practice_no'] ) ? $data['practice_no'] : '', 1, 0, 'L', 0 );
		$pdf->Ln( 20 ); // Line gap
		$pdf->SetFont( 'Calibri', 'B', 12 );
		$pdf->Cell( 80, 10, 'DEFINITIONS' );
		$pdf->Ln( 10 ); // Line gap
		$pdf->SetFont( 'Calibri', '', 12 );
		$pdf->MultiCell( false, 8, $this->convertSpecialCharacters( 'Professional means a psychiatrist, psychologist, therapist, social worker or counsellor listed on Headroom and who continues to be duly registered and authorised to provide his or her services to the public in terms of the rules of the Professional Body/ies which govern their profession. Professional Body includes Health Professions Council of South Africa (HPCSA), South African Council for Social Services Professions (SACSSP) and Association of Supportive Counsellors and Holistic Practitioners (ASCHP). Therapy, for the purposes of this document and its relevance to the services offered on the Headroom website, includes counselling and coaching provided by the Professional.' ) );
		$pdf->Ln( 10 ); // Line gap
		$pdf->SetFont( 'Calibri', 'B', 12 );
		$pdf->Cell( 80, 10, 'INTRODUCTION' );
		$pdf->Ln( 10 ); // Line gap
		$pdf->SetFont( 'Calibri', '', 12 );
		$pdf->MultiCell( false, 8, $this->convertSpecialCharacters( 'Therapy is a regulated relationship between a Client and a Professional that relies on clearly defined rights and responsibilities held by each person. The terms below seek to outline these rights and responsibilities. This form needs to be completed for each new Client – Professional relationship.' ) );
		$pdf->Ln( 10 ); // Line gap
		$pdf->SetFont( 'Calibri', 'B', 12 );
		$pdf->Cell( 80, 10, 'RISKS AND BENEFITS OF THERAPY' );
		$pdf->Ln( 10 ); // Line gap
		$pdf->SetFont( 'Calibri', '', 12 );
		$pdf->MultiCell( false, 8, $this->convertSpecialCharacters( 'Therapy has both benefits and risks for a Client. Risks may include experiencing uncomfortable feelings as the process of Therapy often requires discussing the unpleasant aspects of the Client’s life. However, Therapy has been shown to have benefits for individuals who undertake it. Therapy often leads to a significant reduction in feelings of distress, increased satisfaction in interpersonal relationships, greater personal awareness and insight, increased skills for managing stress and resolutions to specific problems. The Client may feel worse before he/she will start to feel better. The Client must seek treatment voluntarily, remains free to discontinue Therapy at any time or to request a referral to another professional. There are no guarantees regarding the outcome of Therapy, but a proactive effort on the Client’s part will improve the chances of a successful outcome.' ) );
		$pdf->Ln( 10 ); // Line gap
		$pdf->SetFont( 'Calibri', 'B', 12 );
		$pdf->Cell( 80, 10, 'CLIENT RIGHTS' );
		$pdf->Ln( 10 ); // Line gap
		$pdf->SetFont( 'Calibri', '', 12 );
		$pdf->MultiCell( false, 8, $this->convertSpecialCharacters( 'The Client has the right to considerate, safe and respectful care, without discrimination as to race, ethnicity, colour, gender, sexual orientation, age, religion, national origin, or source of payment. He/she has the right to ask questions about any aspects of Therapy and about the Professional’s specific training and experience. If the Client believes that the Professional has acted unethically, the Client may lodge a complaint with the relevant Professional Body under which the Professional is registered.' ) );
		$pdf->Ln( 10 ); // Line gap
		$pdf->SetFont( 'Calibri', 'B', 12 );
		$pdf->Cell( 80, 10, 'ONLINE THERAPY' );
		$pdf->Ln( 10 ); // Line gap
		$pdf->SetFont( 'Calibri', '', 12 );
		$pdf->MultiCell( false, 8, $this->convertSpecialCharacters( 'There are potential benefits and risks of online Therapy that differ from in-person Therapy. Online Therapy may not be an appropriate medium for crisis or emergency situations. The Professional will decide whether or not the Client’s condition being diagnosed or treated is appropriate for online consultation/s. If at any point the Professional determines that online Therapy is not appropriate, the Professional will terminate the online Therapy and provide alternative suggestions and/or an appropriate referral.' ) );
		$pdf->Ln( 10 ); // Line gap
		$pdf->MultiCell( false, 8, $this->convertSpecialCharacters( 'Online Therapy is reliant on technology (including but not limited to live-video, phone, text, email, appointment scheduling) which has many benefits including greater convenience in service delivery. There are however risks in transmitting information over the internet that include, but are not limited to, breaches of confidentiality, theft of personal information, and disruption of service due to technical difficulties.' ) );
		$pdf->Ln( 10 ); // Line gap
		$pdf->MultiCell( false, 8, $this->convertSpecialCharacters( 'Headroom employs various security measures to limit these risks, including but not limited to, HIPAA-compliant software, minimal data storage, passwords protected access, automatic logout time, and data encryption.' ) );
		$pdf->Ln( 10 ); // Line gap
		$pdf->MultiCell( false, 8, $this->convertSpecialCharacters( 'The Client requires access to and familiarity with the appropriate technology to participate in the service provided. No person will be present on the Professional’s side of the online connection, whose presence the Client has not consented to. It is the Client’s responsibility to ensure the confidentiality of his/her communications with the Professional.' ) );
		$pdf->Ln( 10 ); // Line gap
		$pdf->SetFont( 'Calibri', 'B', 12 );
		$pdf->Cell( 80, 10, 'PROFESSIONAL RECORDS' );
		$pdf->Ln( 10 ); // Line gap
		$pdf->SetFont( 'Calibri', '', 12 );
		$pdf->MultiCell( false, 8, $this->convertSpecialCharacters( 'All clinical records will be maintained by the Professional as required by the legal and ethical standards applicable to his/her profession. Except in unusual circumstances that involve danger to the Client, the Client has the right to a copy of such records. Because these are professional records, they may be misinterpreted and / or distressing to untrained readers. It is therefore recommended that should the Client request his/her records, he/she should first review the records with the Professional, or have them forwarded to another mental health professional to discuss the contents. The Professional should be provided a reasonable amount of time to provide copies of the records.' ) );
		$pdf->Ln( 10 ); // Line gap
		$pdf->SetFont( 'Calibri', 'B', 12 );
		$pdf->Cell( 80, 10, 'CONFIDENTIALITY' );
		$pdf->Ln( 10 ); // Line gap
		$pdf->SetFont( 'Calibri', '', 12 );
		$pdf->MultiCell( false, 8, $this->convertSpecialCharacters( 'The laws that protect the confidentiality of any medical information also apply to online Therapy. The Client’s information will only be released with the Client’s express written permission, with the exceptions of the cases as defined by the Professional Body governing the Professional’s conduct. The Client is responsible for securing his/her own hardware, internet access points, and password security. Neither the Professional nor Headroom can be held liable for confidentiality breaches caused by the Client. It is important or the Client to participate in online sessions from a quiet, private space that is free of distractions (including cell phone or other devices) and it is preferable to use a secure internet connection rather than public/free Wi-Fi connection. Client sessions will not be recorded.' ) );
		$pdf->Ln( 10 ); // Line gap
		$pdf->SetFont( 'Calibri', 'B', 12 );
		$pdf->Cell( 80, 10, 'PRIVACY' );
		$pdf->Ln( 10 ); // Line gap
		$pdf->SetFont( 'Calibri', '', 12 );
		$pdf->MultiCell( false, 8, $this->convertSpecialCharacters( 'It is the Professional’s responsibility to maintain privacy of the Client’s personal and Therapy information and communications. Insurance companies authorized by the Client or parties permitted by law may also have access to records or communications.' ) );
		$pdf->Ln( 10 ); // Line gap
		$pdf->SetFont( 'Calibri', 'B', 12 );
		$pdf->Cell( 80, 10, 'CONSENT TO ONLINE THERAPY' );
		$pdf->Ln( 10 ); // Line gap
		$pdf->SetFont( 'Calibri', '', 12 );
		$pdf->MultiCell( false, 8, $this->convertSpecialCharacters( 'I here-by confirm that I understand the risks and limitations of online Therapy. I understand that when I sign this document it represents an agreement between myself and the Professional with whom I have booked a session. I am aware that I can discuss any questions during my session/s with the Professional.' ) );

		$pdf->Ln( 10 ); // Line gap
		$pdf->Cell( 50, 30, 'Client Signature', 1, 0, 'C', 0 );
		$pdf->Cell( 40, 30, $pdf->Image( $signature, $pdf->GetX(), $pdf->GetY(), 40 ), 1, 0, 'C', 0 );
		$pdf->Cell( 30, 30, 'Date', 1, 0, 'C', 0 );
		$pdf->Cell( 60, 30, $data['date'], 1, 0, 'C', 0 );
		$pdf->Ln();
		$pdf->Cell( 50, 30, 'Therapist Signature', 1, 0, 'C', 0 );
		$pdf->Cell( 40, 30, $data['therapist_name'], 1, 0, 'C', 0 );
		$pdf->Cell( 30, 30, 'Date', 1, 0, 'C', 0 );
		$pdf->Cell( 60, 30, $data['date'], 1, 0, 'C', 0 );
		$pdf->Output( 'F', $this->file_path . 'PDF_Client_Informed_Consent-' . $data['therapist_id'] . '.pdf', true );
	}

	private function get_uploads_dir() {
		$uploads    = wp_upload_dir();
		$basedir    = $uploads['basedir'];
		$upload_dir = $basedir . '/webmeeting/';
		if ( ! is_dir( $upload_dir ) ) {
			mkdir( $upload_dir, 0700 );
		}

		return $upload_dir;
	}

	/**
	 * Get User age based on DATE of birth
	 *
	 * @param $dob
	 *
	 * @return bool|int|string
	 */
	private function ageCalculator( $dob ) {
		if ( ! empty( $dob ) ) {
			try {
				$birthdate = new DateTime( $dob );
				$today     = new DateTime( 'today' );
				$age       = $birthdate->diff( $today )->y;
			} catch ( Exception $e ) {
				$age = $e->getMessage();
			}

			return $age;
		} else {
			return false;
		}
	}

	public function convertSpecialCharacters( $content ) {
		return iconv( 'UTF-8', 'windows-1252', $content );
	}
}

new Headroom_ClientConsent();