<?php
/**
 * The booking agreement, described once.
 *
 * Everything else — the form, the validator, the admin screen, the printable
 * record and the CSV export — is generated from this file, so the four of them
 * can never drift apart.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Price list, from the Sept 2026 agreement. Editable under Booking Forms → Settings. */
function pupbf_prices() {
	$defaults = array(
		'weekday_30'    => 15,
		'weekday_60'    => 20,
		'weekend_30'    => 18,
		'weekend_60'    => 25,
		'extra_dog_30'  => 5,
		'extra_dog_60'  => 10,
	);
	$saved = get_option( 'pupbf_prices', array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	$out = array();
	foreach ( $defaults as $k => $v ) {
		$out[ $k ] = isset( $saved[ $k ] ) && '' !== $saved[ $k ] ? (float) $saved[ $k ] : $v;
	}
	return $out;
}

function pupbf_weekdays() {
	return array( 'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday', 'thursday' => 'Thursday', 'friday' => 'Friday' );
}

/**
 * Sections and fields.
 *
 * Field keys:
 *   type      text|tel|email|number|textarea|radio|checkbox|days|signature
 *   label     visible label
 *   help      small print under the field
 *   required  bool
 *   options   for radio  (value => label)
 *   showif    array( field_key, value ) — revealed only when that answer is given
 *   sensitive bool — never included in any email, encrypted at rest
 *   autocomplete  browser autofill hint (big time-saver on a phone)
 */
function pupbf_schema() {
	$schema = array(

		'owner' => array(
			'title'  => 'Your details',
			'blurb'  => 'First, how do I reach you?',
			'fields' => array(
				'owner_name' => array(
					'type'         => 'text',
					'label'        => 'Your full name',
					'required'     => true,
					'autocomplete' => 'name',
				),
				'address' => array(
					'type'         => 'textarea',
					'label'        => 'Address',
					'required'     => true,
					'rows'         => 3,
					'autocomplete' => 'street-address',
				),
				'postcode' => array(
					'type'         => 'text',
					'label'        => 'Postcode',
					'required'     => true,
					'autocomplete' => 'postal-code',
					'class'        => 'pupbf-short',
				),
				'phone' => array(
					'type'         => 'tel',
					'label'        => 'Phone number',
					'required'     => true,
					'autocomplete' => 'tel',
					'inputmode'    => 'tel',
				),
				'email' => array(
					'type'         => 'email',
					'label'        => 'Email address',
					'required'     => true,
					'autocomplete' => 'email',
					'inputmode'    => 'email',
					'help'         => 'I\'ll send your signed copy here.',
				),
			),
		),

		'dogs' => array(
			'title'  => 'Your dog',
			'blurb'  => 'Tell me about the important one.',
			'fields' => array(
				'dog_names' => array(
					'type'     => 'text',
					'label'    => 'Dog name(s)',
					'required' => true,
					'help'     => 'If you have more than one, separate them with a comma.',
				),
				'dog_count' => array(
					'type'     => 'number',
					'label'    => 'How many dogs will be walked?',
					'required' => true,
					'default'  => 1,
					'min'      => 1,
					'max'      => 6,
					'class'    => 'pupbf-short',
				),
				'dog_breeds' => array(
					'type'     => 'text',
					'label'    => 'Breed(s)',
					'required' => true,
				),
				'dog_ages' => array(
					'type'     => 'text',
					'label'    => 'Age(s)',
					'required' => true,
					'class'    => 'pupbf-short',
				),
				'vet_details' => array(
					'type'     => 'textarea',
					'label'    => 'Veterinary practice &amp; phone number',
					'required' => true,
					'rows'     => 2,
				),
				'emergency_name' => array(
					'type'  => 'text',
					'label' => 'Emergency contact name',
					'help'  => 'Someone I can call if I can\'t reach you.',
				),
				'emergency_phone' => array(
					'type'      => 'tel',
					'label'     => 'Emergency contact phone',
					'inputmode' => 'tel',
				),
				'medical' => array(
					'type'  => 'textarea',
					'label' => 'Medical conditions, behavioural issues or special requirements',
					'rows'  => 3,
					'help'  => 'Please tell me anything at all — allergies, recall, reactivity, medication, the lot. It helps me keep them safe.',
				),
			),
		),

		'walks' => array(
			'title'  => 'Your walks',
			'blurb'  => 'When would you like me?',
			'fields' => array(
				'schedule_type' => array(
					'type'     => 'radio',
					'label'    => 'How often?',
					'required' => true,
					'options'  => array(
						'regular' => 'Regular weekly walks',
						'adhoc'   => 'Ad hoc / as needed',
					),
				),
				'days' => array(
					'type'   => 'days',
					'label'  => 'Which days, and roughly what time?',
					'showif' => array( 'schedule_type', 'regular' ),
					'help'   => 'Tick the days you\'d like, then add a rough time for each.',
				),
				'walk_type' => array(
					'type'     => 'radio',
					'label'    => 'Walk length',
					'required' => true,
					'options'  => array(
						'30' => '30-minute walk',
						'60' => '1-hour walk',
					),
				),
				'transport' => array(
					'type'     => 'radio',
					'label'    => 'May I transport your dog in my vehicle?',
					'required' => true,
					'options'  => array(
						'yes' => 'Yes, I authorise transport in the vehicle',
						'no'  => 'No, please walk from my home only',
					),
				),
			),
		),

		'access' => array(
			'title'  => 'Getting in',
			'blurb'  => 'How do I collect your dog?',
			'fields' => array(
				'access_info' => array(
					'type'      => 'textarea',
					'label'     => 'How do I get in, and where will your dog be?',
					'rows'      => 3,
					// Codes are no longer asked for, but people type them anyway.
					// The field stays encrypted and out of every email so that a
					// client ignoring the instruction still isn't exposed.
					'sensitive' => true,
					'help'      => 'For example: side gate is unlocked, dog is in the kitchen, lead hangs by the back door. <strong>Please don\'t put key safe codes or door codes here.</strong> Keycodes should be shared directly with Eddie\'s Pupventures, independently of this form, if required.',
				),
				'recording' => array(
					'type'     => 'radio',
					'label'    => 'Do you have any recording devices at the property?',
					'required' => true,
					'help'     => 'Cameras, doorbells, smart speakers — inside or outside. I just need to know they\'re there.',
					'options'  => array(
						'no'  => 'No recording devices in my home',
						'yes' => 'Yes — I\'ll give details below',
					),
				),
				'recording_details' => array(
					'type'   => 'textarea',
					'label'  => 'Where are they, and are they active?',
					'rows'   => 2,
					'showif' => array( 'recording', 'yes' ),
				),
			),
		),

		'terms' => array(
			'title'  => 'The agreement',
			'blurb'  => 'The boring but important bit.',
			'fields' => array(
				'confirm_vaccinated' => array(
					'type'     => 'checkbox',
					'required' => true,
					'label'    => 'I confirm my dog is fully vaccinated and flea and worm treated',
				),
				'confirm_not_aggressive' => array(
					'type'     => 'checkbox',
					'required' => true,
					'label'    => 'I confirm my dog is not aggressive and poses no risk to people or other animals',
				),
				'vet_authorisation' => array(
					'type'     => 'radio',
					'required' => true,
					'label'    => 'If your dog becomes ill or injured and I can\'t reach you, may I seek veterinary treatment?',
					'help'     => 'All veterinary costs remain the responsibility of the owner.',
					'options'  => array(
						'yes' => 'Yes, I authorise veterinary treatment and understand the costs are mine',
						'no'  => 'No, please keep trying to contact me',
					),
				),
				'photo_optout' => array(
					'type'  => 'checkbox',
					'label' => 'Please do NOT use photos or videos of my dog for promotional purposes',
					'help'  => 'Leave this unticked if you\'re happy for your dog to appear on the website and social media. No personal details are ever shared.',
				),
				'agree_terms' => array(
					'type'     => 'checkbox',
					'required' => true,
					'label'    => 'I have read and agree to the Booking Agreement and Terms &amp; Conditions above',
				),
			),
		),

		'sign' => array(
			'title'  => 'Sign &amp; send',
			'blurb'  => 'Almost done — just your signature.',
			'fields' => array(
				'signed_name' => array(
					'type'         => 'text',
					'label'        => 'Type your full name',
					'required'     => true,
					'autocomplete' => 'name',
				),
				'signature' => array(
					'type'     => 'signature',
					'label'    => 'Sign with your finger',
					'required' => true,
					'help'     => 'Use your finger on a phone or tablet, or your mouse on a computer.',
				),
			),
		),
	);

	/**
	 * Lets the wording be adjusted without editing the plugin.
	 */
	return apply_filters( 'pupbf_schema', $schema );
}

/** Flat map of key => field definition. */
function pupbf_fields() {
	$flat = array();
	foreach ( pupbf_schema() as $section ) {
		foreach ( $section['fields'] as $key => $def ) {
			$flat[ $key ] = $def;
		}
	}
	return $flat;
}

/**
 * The terms text shown above the consent boxes, and reproduced on the printed
 * record so a signed copy is self-contained.
 */
function pupbf_terms_blocks() {
	$p = pupbf_prices();
	$m = function ( $n ) {
		return '£' . rtrim( rtrim( number_format( (float) $n, 2, '.', ',' ), '0' ), '.' );
	};

	return array(
		'Services &amp; prices' => array(
			'Weekday prices — 30-minute walk ' . $m( $p['weekday_30'] ) . ', 1-hour walk ' . $m( $p['weekday_60'] ) . '.',
			'Weekend &amp; bank holiday prices — 30 minutes ' . $m( $p['weekend_30'] ) . ', 1 hour ' . $m( $p['weekend_60'] ) . '.',
			'Additional dogs from the same household — 30 minutes ' . $m( $p['extra_dog_30'] ) . ', 1 hour ' . $m( $p['extra_dog_60'] ) . '.',
			'The prices stated on this form are correct at the time of booking and reflect our current standard pricing. Prices are subject to change from time to time. Where a change to pricing applies, clients will be given a minimum of two weeks\' notice before the new prices take effect.',
			'Where an alternative price has been specifically agreed in writing between the client and Eddie\'s Pupventures that agreed price will take precedence over the prices stated on this form. This includes any existing or individually agreed discounted rates, which will remain applicable in accordance with the terms of that agreement unless otherwise agreed in writing.',
		),
		'Payment terms (strict policy)' => array(
			'All clients are required to make advance payment for services prior to any dog walking sessions being carried out. Payments can be made weekly or monthly.',
			'Upfront payments are non-refundable once services have commenced.',
			'All ad-hoc, one-off, or temporary cover must be paid in full in advance at the time of booking.',
			'No services will be provided without cleared payment.',
			'Late, missed, or partial payments will result in immediate suspension of services.',
			'Eddie\'s Pupventures reserves the right to terminate services if payments are repeatedly late or missed.',
		),
		'Changes &amp; cancellations' => array(
			'A minimum of one (1) week\'s notice is required for cancellations, schedule changes and service reductions.',
			'Provided sufficient notice is given, the applicable credit will be applied to and deducted from the next payment.',
			'Services cancelled without sufficient notice will be charged in full.',
			'Notice must be provided in writing (text or email).',
		),
		'Extreme weather policy' => array(
			'In extreme or unsafe weather conditions (including but not limited to heatwaves, storms, ice, snow, or flooding), dog safety is the priority.',
			'If it is unsafe to walk outdoors, a home check-in visit will be offered. During check-in visits, dogs will be seen and checked on, and let outside if safe to do so.',
			'No refunds or credits will be issued for weather-related changes.',
			'In the event of wet weather, walk durations may be adjusted to allow adequate time for dogs to be dried prior to drop-off.',
		),
		'Health, behaviour &amp; welfare' => array(
			'Owners confirm their dog(s) are fully vaccinated and flea/worm treated, and are not aggressive and pose no risk to people or other animals.',
			'Any medical conditions, behavioural issues, or special requirements must be disclosed before services begin.',
			'Eddie\'s Pupventures reserves the right to refuse or stop services if a dog is deemed unsafe.',
		),
		'Veterinary emergency authorisation' => array(
			'If a dog becomes ill or injured and the owner cannot be contacted, the owner authorises Eddie\'s Pupventures to seek veterinary treatment. All veterinary costs remain the responsibility of the owner.',
		),
		'Keys &amp; home access' => array(
			'Keys and access details will be kept secure and used solely for pet care purposes.',
			'Keycodes should be shared directly with Eddie\'s Pupventures, independently of this form, if required.',
			'Eddie\'s Pupventures is not responsible for issues caused by faulty locks, alarms, or incorrect access information.',
		),
		'Lost dog procedure' => array(
			'In the unlikely event that your dog goes missing while in my care during a walk, I will take reasonable steps to locate and recover your dog as quickly as possible.',
			'I will remain in the immediate area for 15 minutes and actively search for your dog.',
			'Within 30 minutes, I will contact local dog walkers and other relevant contacts to assist with the search.',
			'I will post details of the missing dog on relevant local Facebook groups/pages where appropriate.',
			'I will contact the local Dog Warden or relevant local authority.',
			'I will contact the dog\'s owner to inform them of the situation and provide updates as the search progresses.',
		),
		'Liability' => array(
			'All reasonable care will be taken while providing services.',
			'Dogs are walked and cared for entirely at the owner\'s risk.',
			'Eddie\'s Pupventures will not be held liable for injury, illness, loss, or damage unless caused by proven negligence.',
		),
		'GDPR &amp; data protection (UK)' => array(
			'Personal data is collected solely for the purpose of providing dog walking and pet care services.',
			'Data may include contact details, addresses, veterinary information, and emergency contacts.',
			'All data is stored securely and not shared with third parties, except where legally required or for veterinary care.',
			'Clients may request access to, correction of, or deletion of their personal data at any time.',
			'By signing this agreement, the client consents to data use in line with UK GDPR regulations.',
		),
		'Photo &amp; video consent' => array(
			'The Client grants permission for Eddie\'s Pupventures to take photographs and/or videos of the Client\'s dog during services and to use these images for promotional purposes, including social media and marketing materials. No personal or identifying information about the Client will be shared.',
			'You can opt out below at any time.',
		),
		'Recording devices' => array(
			'The Client agrees to inform Eddie\'s Pupventures, prior to the commencement of any services, of the presence and location of any active or inactive surveillance equipment, including but not limited to cameras, audio recording devices, or monitoring systems, within or around the property. This includes devices located both inside the home and in external areas where services may be performed.',
			'The Client further agrees that any such devices will be used in compliance with applicable privacy and data protection laws. Eddie\'s Pupventures reserves the right to decline or terminate services if undisclosed recording devices are identified or if their presence is deemed to infringe on reasonable expectations of privacy for staff or contractors.',
		),
	);
}
