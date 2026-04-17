/**
 * UNIDAR Internationalization (i18n) Module
 * Supports English (default), French, and Arabic languages
 */

const UNIDAR_I18N = {
    // Current language
    currentLang: 'en',

    // Available languages with metadata
    languages: {
        en: { name: 'English', nativeName: 'English', flag: '🇬🇧', dir: 'ltr' },
        fr: { name: 'French', nativeName: 'Français', flag: '🇫🇷', dir: 'ltr' },
        ar: { name: 'Arabic', nativeName: 'العربية', flag: '🇸🇦', dir: 'rtl' }
    },

    // Translation strings
    translations: {
        en: {
            // Navbar
            nav_home: 'Home',
            nav_listings: 'Listings',
            nav_roommates: 'Roommates',
            nav_dashboard: 'Dashboard',
            nav_messages: 'Messages',
            nav_my_properties: 'My Properties',
            nav_login: 'Login',
            nav_signup: 'Sign Up',
            nav_logout: 'Logout',

            // Home Page
            home_hero_badge: 'Trusted by Students Across Tunisia',
            home_hero_title: 'Find Your Perfect Student Housing',
            home_hero_subtitle: 'The premier platform for Tunisian university students. Discover verified listings, find compatible roommates, and secure safe housing near your campus—all in one place.',
            home_hero_explore: 'Explore Listings',
            home_hero_roommates: 'Find Roommates',
            home_hero_stat_listings: 'Verified Listings',
            home_hero_stat_students: 'Active Students',
            home_hero_stat_unis: 'Universities Covered',

            // Home - Why UNIDAR (Features)
            home_why_tag: 'Why UNIDAR',
            home_why_title: 'Everything You Need for Student Living',
            home_why_subtitle: "We've built the most comprehensive platform to solve every housing challenge students face in Tunisia.",
            home_feat1_title: 'Verified Student Community',
            home_feat1_desc: "Every user is verified with their student ID, ensuring you're always connecting with real, trusted university students and legitimate property owners.",
            home_feat2_title: 'Campus-Based Search',
            home_feat2_desc: "Find housing near your exact campus with our interactive map. Filter by distance, neighborhood, and commute time to find the perfect location.",
            home_feat3_title: 'Smart Roommate Matching',
            home_feat3_desc: "Our intelligent algorithm analyzes lifestyle preferences, sleep schedules, study habits, and more to match you with compatible roommates.",
            home_feat4_title: 'Secure Messaging',
            home_feat4_desc: "Communicate directly with property owners and potential roommates through our built-in messaging system—safe, private, and always accessible.",
            home_feat5_title: 'Detailed Listings',
            home_feat5_desc: "Every listing includes comprehensive details: high-quality photos, amenities, pricing breakdown, house rules, and authentic student reviews.",
            home_feat6_title: 'Real-Time Updates',
            home_feat6_desc: "Get instant notifications when new listings match your criteria or when property owners respond to your inquiries. Never miss an opportunity.",

            // Home - For Students
            home_students_title: 'Built for Students, By Students',
            home_students_subtitle: "We understand the unique challenges of finding student housing in Tunisia. That's why we've created a platform that addresses every pain point—from verifying listings to finding the right roommate.",
            home_students_benefit1: 'Browse hundreds of verified listings near your university',
            home_students_benefit2: 'Get matched with roommates who share your lifestyle',
            home_students_benefit3: 'Contact property owners directly through secure messaging',
            home_students_benefit4: 'Read authentic reviews from fellow students',
            home_students_cta: 'Start Your Search',
            home_visual_match: 'Perfect Match Found!',
            home_visual_compatibility: '98% compatibility score',
            home_visual_new_listing: 'New Listing Near Campus',
            home_visual_amenities_preview: '2 bed • 5 min walk',
            home_visual_verified: 'Student Verified',
            home_visual_uni: 'University of Tunis',

            // Home - For Owners
            home_owners_title: 'Reach Thousands of Verified Students',
            home_owners_subtitle: "List your property on UNIDAR and connect with a targeted audience of verified university students actively searching for housing.",
            home_owners_benefit1: 'Free listing creation with unlimited photos',
            home_owners_benefit2: 'Direct messaging with verified students',
            home_owners_benefit3: 'Manage bookings and inquiries in one dashboard',
            home_owners_benefit4: 'Build trust with student reviews and ratings',
            home_owners_cta: 'List Your Property',
            home_stat_occupancy: 'Occupancy Rate',
            home_stat_response: 'Avg. Response Time',
            home_stat_satisfaction: 'Owner Satisfaction',
            home_stat_started: 'To Get Started',

            // Home - How It Works
            home_how_tag: 'How It Works',
            home_how_title: 'Get Started in 3 Simple Steps',
            home_how_subtitle: "Finding your perfect student housing has never been easier. Here's how UNIDAR simplifies your search.",
            home_step1_title: 'Create Your Profile',
            home_step1_desc: "Sign up as a student or property owner. Verify your identity and set your preferences to get personalized recommendations.",
            home_step2_title: 'Browse & Match',
            home_step2_desc: "Explore verified listings near your campus or use our smart matching to find compatible roommates based on your lifestyle.",
            home_step3_title: 'Connect & Move In',
            home_step3_desc: "Message property owners directly, schedule viewings, and secure your perfect home—all through our safe platform.",

            // Home - Trust
            home_trust_tag: 'Trust & Safety',
            home_trust_title: 'Your Security is Our Priority',
            home_trust_subtitle: "We've implemented multiple layers of verification and security to ensure every interaction on UNIDAR is safe and trustworthy.",
            home_trust_badge1: 'Student ID Verified',
            home_trust_badge2: 'Secure Messaging',
            home_trust_badge3: 'Verified Listings',
            home_trust_badge4: 'Admin Moderated',
            home_trust_badge5: 'Authentic Reviews',

            // Home - Final CTA
            home_cta_title: 'Ready to Find Your Perfect Home?',
            home_cta_subtitle: "Join thousands of Tunisian students who have already found their ideal housing through UNIDAR. Start your search today—it's free!",
            home_cta_btn: 'Get Started for Free',

            // Footer
            footer_brand_desc: "The premier student housing platform in Tunisia. Helping university students find verified housing and compatible roommates since 2026.",
            footer_platform: 'Platform',
            footer_for_owners: 'For Owners',
            footer_support: 'Support',
            footer_browse: 'Browse Listings',
            footer_find_roommates: 'Find Roommates',
            footer_list_property: 'List Property',
            footer_manage: 'Manage Listings',
            footer_help: 'Help Center',
            footer_contact: 'Contact Us',
            footer_report: 'Report Issue',
            footer_copyright: "© 2026 UNIDAR. All rights reserved. Made with ❤️ for Tunisian students.",

            // Common actions
            btn_save: 'Save',
            btn_cancel: 'Cancel',
            btn_submit: 'Submit',
            btn_edit: 'Edit',
            btn_delete: 'Delete',
            btn_view_details: 'Details',
            btn_send_message: 'Send Message',
            btn_apply_filters: 'Apply Filters',
            btn_browse_all: 'Browse All',
            btn_find_more: 'Find More',

            // Listings page
            listings_title: 'Find Your Perfect Home',
            listings_marketplace: 'Marketplace',
            listings_map_view: 'Map View',
            listings_list_view: 'List View',
            filter_min_price: 'Min Price (TND)',
            filter_max_price: 'Max Price (TND)',
            filter_bedrooms: 'Bedrooms',
            filter_any_bedrooms: 'Any Bedrooms',
            filter_property_type: 'Property Type',
            filter_any_type: 'Any Type',
            filter_apartment: 'Apartment',
            filter_house: 'House',
            filter_studio: 'Studio',
            filter_shared_room: 'Shared Room',
            listings_no_results: 'No Listings Found',
            listings_adjust_filters: 'Try adjusting your filters',
            listings_per_month: '/mo',

            // Roommates page
            roommates_title: 'Find Roommates',
            roommates_compatibility: 'Compatibility',
            roommates_preferences: 'Preferences',
            roommates_no_matches: 'No Matches Yet',
            roommates_set_preferences: 'Set your preferences to find compatible roommates',
            roommates_message_btn: 'Message',
            roommates_verified: 'Verified',
            btn_set_preferences: 'Set Preferences',
            pref_budget: 'Budget',
            pref_budget_min: 'Budget Min',
            pref_budget_max: 'Budget Max',
            pref_cleanliness: 'Cleanliness',
            pref_cleanliness_level: 'Cleanliness Level',
            pref_no_preference: 'No preference',
            pref_very_clean: 'Very Clean',
            pref_clean: 'Clean',
            pref_moderate: 'Moderate',
            pref_relaxed: 'Relaxed',
            pref_smoking: 'Smoking',
            pref_smoking_preference: 'Smoking Preference',
            pref_non_smoker: 'Non-Smoker',
            pref_smoker: 'Smoker',
            pref_sleep: 'Sleep',
            pref_sleep_schedule: 'Sleep Schedule',
            pref_early_riser: 'Early Riser',
            pref_normal: 'Normal',
            pref_night_owl: 'Night Owl',
            pref_save_refresh: 'Save & Refresh Results',

            // Dashboard
            dashboard_title: 'Dashboard',
            dashboard_welcome: 'Welcome back',
            dashboard_portal: 'Portal',
            dashboard_saved: 'Saved',
            dashboard_matches: 'Matches',
            dashboard_unread: 'Unread',
            dashboard_nearby: 'Nearby',
            dashboard_edit_profile: 'Edit Profile',
            dashboard_saved_listings: 'Saved for Later',
            dashboard_neighborhood: 'Neighborhood Watch',
            dashboard_nearby_listings: 'Listings within 10km of your area',
            dashboard_matchmaking: 'Matchmaking',
            dashboard_conversations: 'Conversations',
            dashboard_inbox: 'Inbox',

            // Login/Register
            login_page_title: 'Login - UNIDAR',
            login_title: 'Sign In',
            login_welcome: 'Welcome Back',
            login_access: 'Access your UNIDAR account',
            login_email: 'Email Address',
            login_password: 'Password',
            login_forgot: 'Forgot password?',
            login_signin: 'Sign In',
            login_no_account: "Don't have an account?",
            login_create: 'Create Account',
            login_email_placeholder: 'student@university.edu',
            login_password_placeholder: '••••••••',
            login_signing_in: 'Signing in...',

            register_title: 'Create Account',
            register_welcome: 'Join US',
            register_join: 'Join UNIDAR',
            register_start: 'Start your journey with UNIDAR',
            register_fullname: 'Full Name',
            register_fullname_placeholder: 'John Doe',
            register_email: 'Email Address',
            register_email_placeholder: 'student@university.edu',
            register_email_hint: 'University email helps with trust and verification.',
            register_password: 'Password',
            register_password_placeholder: 'Min. 8 characters',
            register_confirm: 'Confirm Password',
            register_confirm_placeholder: 'Re-enter password',
            register_phone: 'Phone Number (Optional)',
            register_phone_placeholder: '+216 ...',
            register_role: 'I am a',
            register_role_select: 'Select...',
            register_student: 'Student',
            register_owner: 'Property Owner',
            register_university: 'University Name',
            register_university_placeholder: 'e.g. ESPRIT, INSAT, MSB...',
            register_map_radius: 'Search Radius (Preferred Area)',
            register_map_hint: 'Click on the map to mark your study area.',
            register_selected_location: 'Selected:',
            register_agree: 'I agree to the Terms of Service',
            register_create: 'Create Account',
            register_creating_account: 'Creating account...',
            register_have_account: 'Already have an account?',
            register_signin: 'Sign In',

            // Listing detail
            detail_back: 'Back to Listings',
            detail_bedrooms: 'Bedrooms',
            detail_bathrooms: 'Bathrooms',
            detail_size: 'Size',
            detail_type: 'Type',
            detail_description: 'Description',
            detail_amenities: 'Amenities',
            detail_location: 'Location',
            detail_contact_owner: 'Contact Owner',
            detail_save_listing: 'Save Listing',
            detail_saved: 'Saved',

            // Owner listings
            owner_my_properties: 'My Properties',
            owner_add_property: 'Add Property',
            owner_no_properties: 'No properties yet',
            owner_create_first: 'Create your first listing',
            owner_total_properties: 'Total Properties',
            owner_active_listings: 'Active Listings',
            owner_total_inquiries: 'Total Inquiries',
            owner_rented_properties: 'Rented Properties',
            owner_management: 'Management',
            owner_existing_properties: 'Existing Properties',
            owner_refresh_list: 'Refresh List',
            owner_add_new_property: 'Add New Property',
            owner_title_label: 'Title',
            owner_description_label: 'Description',
            owner_address_label: 'Address',
            owner_price_label: 'Price per month (TND)',
            owner_type_label: 'Property Type',
            owner_bedrooms_label: 'Bedrooms',
            owner_bathrooms_label: 'Bathrooms',
            owner_available_label: 'Available From',
            owner_loading_properties: 'Loading your properties...',
            owner_property_portfolio: 'Property Portfolio',
            owner_portfolio_desc: 'The geographic distribution of your properties',

            // Verification
            verification_title: 'Student Verification',
            verification_upload: 'Upload your student ID',
            verification_pending: 'Verification Pending',
            verification_approved: 'Verified Student',

            // Footer
            footer_tagline: 'Safe Student Housing',
            footer_rights: 'All rights reserved',

            // Chat Widget
            chat_messages: 'Messages',
            chat_active: 'Active',
            chat_archived: 'Archived',
            chat_no_messages: 'No messages.',
            chat_type_message: 'Type a message...',
            chat_send: 'Send',
            chat_archive: 'Archive',
            chat_unarchive: 'Unarchive',
            chat_delete: 'Delete',
            chat_block: 'Block User',
            chat_report: 'Report',
            chat_loading: 'Loading conversations...',

            // Listing Cards & Detail
            card_bedrooms: 'Bedrooms',
            card_bathrooms: 'Bathrooms',
            card_bath: 'Bath',
            card_per_month: '/mo',
            card_details: 'Details',
            card_no_listings: 'No Listings Found',
            card_adjust_filters: 'Try adjusting your filters',
            card_save: 'Save To Favorites',
            card_saved: 'Saved',
            card_contact: 'Contact Owner',
            card_about: 'About this place',
            card_available: 'Available',
            card_available_from: 'Available From',
            card_location: 'Location',
            card_share: 'Share',
            card_report: 'Report a problem with this listing',
            card_per_month_full: '/ month',
            card_no_description: 'No description provided.',
            card_generate_contract: 'Generate Contract',

            // Misc
            loading: 'Loading...',
            error_occurred: 'An error occurred',
            verified: 'Verified',
            student: 'Student',

            // Dynamic Listings Content
            bedrooms_short: 'Bed',
            bedrooms_abbr: 'Ch.',
            places: 'Places',
            places_left: 'place(s) left',
            full_badge: 'FULL 👥',
            discover_offer: 'Discover this offer →',
            view_details_arrow: 'View Details →',
            loading_marketplace: 'Loading Marketplace...',
            failed_load: 'Failed to load listings. Please try again.',
            role_student: 'Student',

            // View Toggle
            map_view: 'Map View',
            list_view: 'List View',

            // Filter Placeholders
            placeholder_min_price: 'e.g. 400',
            placeholder_max_price: 'e.g. 1200',

            // Footer Links
            footer_about: 'About',
            footer_help_link: 'Help',
            footer_privacy: 'Privacy',
            footer_terms: 'Terms',

            // Common UI
            explore_listings_text: 'Explore high-quality student housing options verified for your safety and comfort.',
            per_month: '/mo',
            month_abbr: '/mo',
            sqm: 'm²',

            // Bedroom Options
            bedroom_1: '1 Bedroom',
            bedroom_2: '2 Bedrooms',
            bedroom_3: '3 Bedrooms',
            bedroom_4: '4+ Bedrooms',

            // Map Popup
            view_details_link: 'View Details'
        },

        fr: {
            // Navbar
            nav_home: 'Accueil',
            nav_listings: 'Annonces',
            nav_roommates: 'Colocataires',
            nav_dashboard: 'Tableau de bord',
            nav_messages: 'Messages',
            nav_my_properties: 'Mes Propriétés',
            nav_login: 'Connexion',
            nav_signup: 'Inscription',
            nav_logout: 'Déconnexion',

            // Home Page
            home_hero_badge: 'Approuvé par des étudiants à travers toute la Tunisie',
            home_hero_title: 'Trouvez votre logement étudiant idéal',
            home_hero_subtitle: 'La première plateforme pour les étudiants universitaires tunisiens. Découvrez des annonces vérifiées, trouvez des colocataires compatibles et sécurisez un logement sûr près de votre campus.',
            home_hero_explore: 'Explorer les annonces',
            home_hero_roommates: 'Trouver des colocataires',
            home_hero_stat_listings: 'Annonces vérifiées',
            home_hero_stat_students: 'Étudiants actifs',
            home_hero_stat_unis: 'Universités couvertes',

            // Home - Why UNIDAR (Features)
            home_why_tag: 'Pourquoi UNIDAR',
            home_why_title: 'Tout ce dont vous avez besoin pour la vie étudiante',
            home_why_subtitle: "Nous avons construit la plateforme la plus complète pour résoudre tous les défis de logement auxquels les étudiants sont confrontés en Tunisie.",
            home_feat1_title: 'Communauté étudiante vérifiée',
            home_feat1_desc: "Chaque utilisateur est vérifié avec sa carte d'étudiant, ce qui garantit que vous êtes toujours en contact avec de vrais étudiants universitaires de confiance et des propriétaires légitimes.",
            home_feat2_title: 'Recherche par campus',
            home_feat2_desc: "Trouvez un logement près de votre campus exact grâce à notre carte interactive. Filtrez par distance, quartier et temps de trajet pour trouver l'emplacement idéal.",
            home_feat3_title: 'Mise en relation intelligente',
            home_feat3_desc: "Notre algorithme intelligent analyse les préférences de style de vie, les horaires de sommeil, les habitudes d'étude et plus encore pour vous mettre en relation avec des colocataires compatibles.",
            home_feat4_title: 'Messagerie sécurisée',
            home_feat4_desc: "Communiquez directement avec les propriétaires et les colocataires potentiels via notre système de messagerie intégré — sûr, privé et toujours accessible.",
            home_feat5_title: 'Annonces détaillées',
            home_feat5_desc: "Chaque annonce comprend des détails complets : photos de haute qualité, équipements, ventilation des prix, règles de la maison et avis d'étudiants authentiques.",
            home_feat6_title: 'Mises à jour en temps réel',
            home_feat6_desc: "Recevez des notifications instantanées lorsque de nouvelles annonces correspondent à vos critères ou lorsque les propriétaires répondent à vos demandes. Ne manquez jamais une opportunité.",

            // Home - For Students
            home_students_title: 'Conçu pour les étudiants, par des étudiants',
            home_students_subtitle: "Nous comprenons les défis uniques de la recherche de logement étudiant en Tunisie. C'est pourquoi nous avons créé une plateforme qui répond à chaque problème — de la vérification des annonces à la recherche du bon colocataire.",
            home_students_benefit1: 'Parcourez des centaines d\'annonces vérifiées près de votre université',
            home_students_benefit2: 'Soyez mis en relation avec des colocataires qui partagent votre style de vie',
            home_students_benefit3: 'Contactez les propriétaires directement via une messagerie sécurisée',
            home_students_benefit4: 'Lisez des avis authentiques d\'autres étudiants',
            home_students_cta: 'Commencer votre recherche',
            home_visual_match: 'Correspondance parfaite trouvée !',
            home_visual_compatibility: 'Score de compatibilité de 98%',
            home_visual_new_listing: 'Nouvelle annonce près du campus',
            home_visual_amenities_preview: '2 lits • 5 min à pied',
            home_visual_verified: 'Étudiant vérifié',
            home_visual_uni: 'Université de Tunis',

            // Home - For Owners
            home_owners_title: 'Atteignez des milliers d\'étudiants vérifiés',
            home_owners_subtitle: "Inscrivez votre propriété sur UNIDAR et connectez-vous avec un public ciblé d'étudiants universitaires vérifiés qui recherchent activement un logement.",
            home_owners_benefit1: 'Création d\'annonce gratuite avec photos illimitées',
            home_owners_benefit2: 'Messagerie directe avec des étudiants vérifiés',
            home_owners_benefit3: 'Gérez les réservations et les demandes dans un seul tableau de bord',
            home_owners_benefit4: 'Instaurez la confiance grâce aux avis et notes des étudiants',
            home_owners_cta: 'Inscrire votre propriété',
            home_stat_occupancy: 'Taux d\'occupation',
            home_stat_response: 'Délai de réponse moyen',
            home_stat_satisfaction: 'Satisfaction des propriétaires',
            home_stat_started: 'Pour commencer',

            // Home - How It Works
            home_how_tag: 'Comment ça marche',
            home_how_title: 'Commencez en 3 étapes simples',
            home_how_subtitle: "Trouver votre logement étudiant idéal n'a jamais été aussi facile. Voici comment UNIDAR simplifie votre recherche.",
            home_step1_title: 'Créez votre profil',
            home_step1_desc: "Inscrivez-vous en tant qu'étudiant ou propriétaire. Vérifiez votre identité et définissez vos préférences pour obtenir des recommandations personnalisées.",
            home_step2_title: 'Parcourez et faites correspondre',
            home_step2_desc: "Explorez les annonces vérifiées près de votre campus ou utilisez notre mise en relation intelligente pour trouver des colocataires compatibles en fonction de votre style de vie.",
            home_step3_title: 'Connectez-vous et emménagez',
            home_step3_desc: "Envoyez des messages directs aux propriétaires, planifiez des visites et sécurisez votre maison idéale — le tout via notre plateforme sécurisée.",

            // Home - Trust
            home_trust_tag: 'Confiance et sécurité',
            home_trust_title: 'Votre sécurité est notre priorité',
            home_trust_subtitle: "Nous avons mis en place plusieurs couches de vérification et de sécurité pour garantir que chaque interaction sur UNIDAR est sûre et digne de confiance.",
            home_trust_badge1: 'ID étudiant vérifié',
            home_trust_badge2: 'Messagerie sécurisée',
            home_trust_badge3: 'Annonces vérifiées',
            home_trust_badge4: 'Modéré par l\'administrateur',
            home_trust_badge5: 'Avis authentiques',

            // Home - Final CTA
            home_cta_title: 'Prêt à trouver votre logement idéal ?',
            home_cta_subtitle: "Rejoignez des milliers d'étudiants tunisiens qui ont déjà trouvé leur logement idéal grâce à UNIDAR. Commencez votre recherche aujourd'hui — c'est gratuit !",
            home_cta_btn: 'Commencer gratuitement',

            // Footer
            footer_brand_desc: "La première plateforme de logement étudiant en Tunisie. Aider les étudiants universitaires à trouver un logement vérifié et des colocataires compatibles depuis 2026.",
            footer_platform: 'Plateforme',
            footer_for_owners: 'Pour les propriétaires',
            footer_support: 'Support',
            footer_browse: 'Parcourir les annonces',
            footer_find_roommates: 'Trouver des colocataires',
            footer_list_property: 'Inscrire une propriété',
            footer_manage: 'Gérer les annonces',
            footer_help: 'Centre d\'aide',
            footer_contact: 'Nous contacter',
            footer_report: 'Signaler un problème',
            footer_copyright: "© 2026 UNIDAR. Tous droits réservés. Fait avec ❤️ pour les étudiants tunisiens.",

            // Common actions
            btn_save: 'Enregistrer',
            btn_cancel: 'Annuler',
            btn_submit: 'Soumettre',
            btn_edit: 'Modifier',
            btn_delete: 'Supprimer',
            btn_view_details: 'Détails',
            btn_send_message: 'Envoyer un message',
            btn_apply_filters: 'Appliquer les filtres',
            btn_browse_all: 'Voir tout',
            btn_find_more: 'Trouver plus',

            // Listings page
            listings_title: 'Trouvez Votre Logement Idéal',
            listings_marketplace: 'Marché',
            listings_map_view: 'Vue carte',
            listings_list_view: 'Vue liste',
            filter_min_price: 'Prix min (TND)',
            filter_max_price: 'Prix max (TND)',
            filter_bedrooms: 'Chambres',
            filter_any_bedrooms: 'Toutes chambres',
            filter_property_type: 'Type de bien',
            filter_any_type: 'Tous types',
            filter_apartment: 'Appartement',
            filter_house: 'Maison',
            filter_studio: 'Studio',
            filter_shared_room: 'Chambre partagée',
            listings_no_results: 'Aucune annonce trouvée',
            listings_adjust_filters: 'Essayez d\'ajuster vos filtres',
            listings_per_month: '/mois',

            // Roommates page
            roommates_title: 'Trouver des Colocataires',
            roommates_compatibility: 'Compatibilité',
            roommates_preferences: 'Préférences',
            roommates_no_matches: 'Pas encore de correspondances',
            roommates_set_preferences: 'Définissez vos préférences pour trouver des colocataires compatibles',
            roommates_message_btn: 'Message',
            roommates_verified: 'Vérifié',
            btn_set_preferences: 'Définir les préférences',
            pref_budget: 'Budget',
            pref_budget_min: 'Budget min',
            pref_budget_max: 'Budget max',
            pref_cleanliness: 'Propreté',
            pref_cleanliness_level: 'Niveau de propreté',
            pref_no_preference: 'Pas de préférence',
            pref_very_clean: 'Très propre',
            pref_clean: 'Propre',
            pref_moderate: 'Modéré',
            pref_relaxed: 'Détendu',
            pref_smoking: 'Fumeur',
            pref_smoking_preference: 'Préférence fumeur',
            pref_non_smoker: 'Non-fumeur',
            pref_smoker: 'Fumeur',
            pref_sleep: 'Sommeil',
            pref_sleep_schedule: 'Horaire de sommeil',
            pref_early_riser: 'Lève-tôt',
            pref_normal: 'Normal',
            pref_night_owl: 'Couche-tard',
            pref_save_refresh: 'Enregistrer et actualiser',

            // Dashboard
            dashboard_title: 'Tableau de bord',
            dashboard_welcome: 'Bienvenue',
            dashboard_portal: 'Portail',
            dashboard_saved: 'Favoris',
            dashboard_matches: 'Correspondances',
            dashboard_unread: 'Non lus',
            dashboard_nearby: 'À proximité',
            dashboard_edit_profile: 'Modifier le profil',
            dashboard_saved_listings: 'Enregistrés pour plus tard',
            dashboard_neighborhood: 'Surveillance du quartier',
            dashboard_nearby_listings: 'Annonces dans un rayon de 10km',
            dashboard_matchmaking: 'Correspondances',
            dashboard_conversations: 'Conversations',
            dashboard_inbox: 'Boîte de réception',

            // Login/Register
            login_page_title: 'Connexion - UNIDAR',
            login_title: 'Connexion',
            login_welcome: 'Bon retour',
            login_access: 'Accédez à votre compte UNIDAR',
            login_email: 'Adresse e-mail',
            login_password: 'Mot de passe',
            login_forgot: 'Mot de passe oublié?',
            login_signin: 'Se connecter',
            login_no_account: 'Pas encore de compte?',
            login_create: 'Créer un compte',
            login_email_placeholder: 'etudiant@universite.tn',
            login_password_placeholder: '••••••••',
            login_signing_in: 'Connexion en cours...',

            register_title: 'Créer un compte',
            register_welcome: 'Rejoignez-nous',
            register_join: 'Rejoindre UNIDAR',
            register_start: 'Commencez votre voyage avec UNIDAR',
            register_fullname: 'Nom complet',
            register_fullname_placeholder: 'Jean Dupont',
            register_email: 'Adresse e-mail',
            register_email_placeholder: 'etudiant@universite.tn',
            register_email_hint: 'L\'e-mail universitaire renforce la confiance et facilite la vérification.',
            register_password: 'Mot de passe',
            register_password_placeholder: 'Min. 8 caractères',
            register_confirm: 'Confirmer le mot de passe',
            register_confirm_placeholder: 'Saisissez à nouveau le mot de passe',
            register_phone: 'Numéro de téléphone (Optionnel)',
            register_phone_placeholder: '+216 ...',
            register_role: 'Je suis un(e)',
            register_role_select: 'Sélectionner...',
            register_student: 'Étudiant',
            register_owner: 'Propriétaire',
            register_university: 'Nom de l\'université',
            register_university_placeholder: 'ex. ESPRIT, INSAT, MSB...',
            register_map_radius: 'Rayon de recherche (Zone préférée)',
            register_map_hint: 'Cliquez sur la carte pour marquer votre zone d\'étude.',
            register_selected_location: 'Sélectionné:',
            register_agree: 'J\'accepte les conditions d\'utilisation',
            register_create: 'Créer un compte',
            register_creating_account: 'Création du compte...',
            register_have_account: 'Vous avez déjà un compte?',
            register_signin: 'Se connecter',

            // Listing detail
            detail_back: 'Retour aux annonces',
            detail_bedrooms: 'Chambres',
            detail_bathrooms: 'Salles de bain',
            detail_size: 'Surface',
            detail_type: 'Type',
            detail_description: 'Description',
            detail_amenities: 'Équipements',
            detail_location: 'Emplacement',
            detail_contact_owner: 'Contacter le propriétaire',
            detail_save_listing: 'Sauvegarder',
            detail_saved: 'Sauvegardé',

            // Owner listings
            owner_my_properties: 'Mes Propriétés',
            owner_add_property: 'Ajouter une propriété',
            owner_no_properties: 'Pas encore de propriétés',
            owner_create_first: 'Créez votre première annonce',
            owner_total_properties: 'Propriétés totales',
            owner_active_listings: 'Annonces actives',
            owner_total_inquiries: 'Demandes totales',
            owner_rented_properties: 'Propriétés louées',
            owner_management: 'Gestion',
            owner_existing_properties: 'Propriétés existantes',
            owner_refresh_list: 'Actualiser la liste',
            owner_add_new_property: 'Ajouter une nouvelle propriété',
            owner_title_label: 'Titre',
            owner_description_label: 'Description',
            owner_address_label: 'Adresse',
            owner_price_label: 'Prix par mois (TND)',
            owner_type_label: 'Type de bien',
            owner_bedrooms_label: 'Chambres',
            owner_bathrooms_label: 'Salles de bain',
            owner_available_label: 'Disponible à partir de',
            owner_loading_properties: 'Chargement de vos propriétés...',
            owner_property_portfolio: 'Portefeuille immobilier',
            owner_portfolio_desc: 'La répartition géographique de vos propriétés',

            // Verification
            verification_title: 'Vérification étudiant',
            verification_upload: 'Téléchargez votre carte d\'étudiant',
            verification_pending: 'Vérification en attente',
            verification_approved: 'Étudiant vérifié',

            // Footer
            footer_tagline: 'Logement étudiant sécurisé',
            footer_rights: 'Tous droits réservés',

            // Chat Widget
            chat_messages: 'Messages',
            chat_active: 'Actifs',
            chat_archived: 'Archivés',
            chat_no_messages: 'Aucun message.',
            chat_type_message: 'Écrire un message...',
            chat_send: 'Envoyer',
            chat_archive: 'Archiver',
            chat_unarchive: 'Désarchiver',
            chat_delete: 'Supprimer',
            chat_block: 'Bloquer l\'utilisateur',
            chat_report: 'Signaler',
            chat_loading: 'Chargement des conversations...',

            // Listing Cards & Detail
            card_bedrooms: 'Chambres',
            card_bathrooms: 'Salles de bain',
            card_bath: 'Bain',
            card_per_month: '/mois',
            card_details: 'Détails',
            card_no_listings: 'Aucune annonce trouvée',
            card_adjust_filters: 'Essayez d\'ajuster vos filtres',
            card_save: 'Sauvegarder',
            card_saved: 'Sauvegardé',
            card_contact: 'Contacter le propriétaire',
            card_about: 'À propos de ce logement',
            card_available: 'Disponible',
            card_available_from: 'Disponible à partir de',
            card_location: 'Emplacement',
            card_share: 'Partager',
            card_report: 'Signaler un problème avec cette annonce',
            card_per_month_full: '/ mois',
            card_no_description: 'Aucune description fournie.',
            card_generate_contract: 'Générer le Contrat',

            // Misc
            loading: 'Chargement...',
            error_occurred: 'Une erreur s\'est produite',
            verified: 'Vérifié',
            student: 'Étudiant',

            // Dynamic Listings Content
            bedrooms_short: 'Lit',
            bedrooms_abbr: 'Ch.',
            places: 'Places',
            places_left: 'place(s) restante(s)',
            full_badge: 'COMPLET 👥',
            discover_offer: 'Découvrez cette offre →',
            view_details_arrow: 'Voir les détails →',
            loading_marketplace: 'Chargement du marché...',
            failed_load: 'Échec du chargement des annonces. Veuillez réessayer.',
            role_student: 'Étudiant',

            // View Toggle
            map_view: 'Vue carte',
            list_view: 'Vue liste',

            // Filter Placeholders
            placeholder_min_price: 'ex. 400',
            placeholder_max_price: 'ex. 1200',

            // Footer Links
            footer_about: 'À propos',
            footer_help_link: 'Aide',
            footer_privacy: 'Confidentialité',
            footer_terms: 'Conditions',

            // Common UI
            explore_listings_text: 'Explorez des options de logement étudiant de haute qualité vérifiées pour votre sécurité et votre confort.',
            per_month: '/mois',
            month_abbr: '/mois',
            sqm: 'm²',

            // Bedroom Options
            bedroom_1: '1 Chambre',
            bedroom_2: '2 Chambres',
            bedroom_3: '3 Chambres',
            bedroom_4: '4+ Chambres',

            // Map Popup
            view_details_link: 'Voir les détails'
        },

        ar: {
            // Navbar
            nav_home: 'الرئيسية',
            nav_listings: 'الإعلانات',
            nav_roommates: 'زملاء السكن',
            nav_dashboard: 'لوحة التحكم',
            nav_messages: 'الرسائل',
            nav_my_properties: 'عقاراتي',
            nav_login: 'تسجيل الدخول',
            nav_signup: 'إنشاء حساب',
            nav_logout: 'تسجيل الخروج',

            // Home Page
            home_hero_badge: 'موثوق به من قبل الطلاب في جميع أنحاء تونس',
            home_hero_title: 'ابحث عن سكنك الطلابي المثالي',
            home_hero_subtitle: 'المنصة الأولى لطلاب الجامعات التونسية. اكتشف إعلانات موثقة، ابحث عن زملاء سكن متوافقين، واحصل على سكن آمن بالقرب من حرمك الجامعي - كل ذلك في مكان واحد.',
            home_hero_explore: 'استكشاف الإعلانات',
            home_hero_roommates: 'البحث عن زملاء سكن',
            home_hero_stat_listings: 'إعلانات موثقة',
            home_hero_stat_students: 'طلاب نشطون',
            home_hero_stat_unis: 'جامعة مغطاة',

            // Home - Why UNIDAR (Features)
            home_why_tag: 'لماذا UNIDAR',
            home_why_title: 'كل ما تحتاجه للحياة الطلابية',
            home_why_subtitle: 'لقد قمنا ببناء المنصة الأكثر شمولاً لحل كل تحديات السكن التي يواجهها الطلاب في تونس.',
            home_feat1_title: 'مجتمع طلابي موثق',
            home_feat1_desc: 'يتم التحقق من كل مستخدم من خلال بطاقة الطالب الخاصة به، مما يضمن تواصلك دائماً مع طلاب جامعيين حقيقيين وموثوقين وأصحاب عقارات شرعيين.',
            home_feat2_title: 'البحث بناءً على الحرم الجامعي',
            home_feat2_desc: 'ابحث عن سكن بالقرب من حرمك الجامعي بالضبط باستخدام خريطتنا التفاعلية. فلتبر حسب المسافة والحي ووقت التنقل للعثور على الموقع المثالي.',
            home_feat3_title: 'مطابقة ذكية لزملاء السكن',
            home_feat3_desc: 'يقوم خوارزمنا الذكي بتحليل تفضيلات نمط الحياة وجداول النوم وعادات الدراسة وغيرها لمطابقتك مع زملاء سكن متوافقين.',
            home_feat4_title: 'رسائل آمنة',
            home_feat4_desc: 'تواصل مباشرة مع أصحاب العقارات وزملاء السكن المحتملين من خلال نظام الرسائل المدمج لدينا - آمن وخاص ومتاح دائماً.',
            home_feat5_title: 'إعلانات مفصلة',
            home_feat5_desc: 'يتضمن كل إعلان تفاصيل شاملة: صور عالية الجودة، المرافق، تفاصيل الأسعار، قواعد المنزل، ومراجعات طلابية حقيقية.',
            home_feat6_title: 'تحديثات في الوقت الفعلي',
            home_feat6_desc: 'احصل على إشعارات فورية عندما تتطابق إعلانات جديدة مع معاييرك أو عندما يستجيب أصحاب العقارات لاستفساراتك. لا تفوت أي فرصة أبداً.',

            // Home - For Students
            home_students_title: 'بني للطلاب، بواسطة الطلاب',
            home_students_subtitle: 'نحن نتفهم التحديات الفريدة للبحث عن سكن طلابي في تونس. لهذا السبب أنشأنا منصة تعالج كل نقاط الألم - من توثيق الإعلانات إلى العثور على زميل السكن المناسب.',
            home_students_benefit1: 'تصفح مئات الإعلانات الموثقة بالقرب من جامعتك',
            home_students_benefit2: 'احصل على مطابقة مع زملاء سكن يشاركونك نمط حياتك',
            home_students_benefit3: 'تواصل مع أصحاب العقارات مباشرة من خلال رسائل آمنة',
            home_students_benefit4: 'اقرأ مراجعات حقيقية من زملائك الطلاب',
            home_students_cta: 'ابدأ بحثك',
            home_visual_match: 'تم العثور على المطابقة المثالية!',
            home_visual_compatibility: 'درجة توافق 98%',
            home_visual_new_listing: 'إعلان جديد بالقرب من الحرم الجامعي',
            home_visual_amenities_preview: '2 سرير • 5 دقائق سيراً',
            home_visual_verified: 'طالب موثق',
            home_visual_uni: 'جامعة تونس',

            // Home - For Owners
            home_owners_title: 'الوصول إلى آلاف الطلاب الموثقين',
            home_owners_subtitle: 'أدرج عقارك على UNIDAR وتواصل مع جمهور مستهدف من طلاب الجامعات الموثقين الذين يبحثون بنشاط عن سكن.',
            home_owners_benefit1: 'إنشاء إعلانات مجانية مع صور غير محدودة',
            home_owners_benefit2: 'رسائل مباشرة مع طلاب موثقين',
            home_owners_benefit3: 'إدارة الحجوزات والاستفسارات في لوحة تحكم واحدة',
            home_owners_benefit4: 'بناء الثقة من خلال مراجعات الطلاب وتقييماتهم',
            home_owners_cta: 'أدرج عقارك',
            home_stat_occupancy: 'معدل الإشغال',
            home_stat_response: 'متوسط وقت الاستجابة',
            home_stat_satisfaction: 'رضا المالك',
            home_stat_started: 'للبدء',

            // Home - How It Works
            home_how_tag: 'كيف يعمل',
            home_how_title: 'ابدأ في 3 خطوات بسيطة',
            home_how_subtitle: 'لم يكن العثور على سكنك الطلابي المثالي أسهل من أي وقت مضى. إليك كيف يبسط UNIDAR بحثك.',
            home_step1_title: 'أنشئ ملفك الشخصي',
            home_step1_desc: 'سجل كطالب أو صاحب عقار. تحقق من هويتك وحدد تفضيلاتك للحصول على توصيات مخصصة.',
            home_step2_title: 'تصفح وطابق',
            home_step2_desc: 'استكشف الإعلانات الموثقة بالقرب من حرمك الجامعي أو استخدم مطابقتنا الذكية للعثور على زملاء سكن متوافقين بناءً على نمط حياتك.',
            home_step3_title: 'تواصل وانتقل',
            home_step3_desc: 'راسل أصحاب العقارات مباشرة، وحدد مواعيد للمعاينة، وأمن منزلك المثالي - كل ذلك من خلال منصتنا الآمنة.',

            // Home - Trust
            home_trust_tag: 'الثقة والأمان',
            home_trust_title: 'أمنك هو أولويتنا',
            home_trust_subtitle: 'لقد قمنا بوضع طبقات متعددة من التحقق والأمان لضمان أن يكون كل تفاعل على UNIDAR آمناً وموثوقاً.',
            home_trust_badge1: 'تم التحقق من هوية الطالب',
            home_trust_badge2: 'رسائل آمنة',
            home_trust_badge3: 'إعلانات موثقة',
            home_trust_badge4: 'مراقب من قبل الإدارة',
            home_trust_badge5: 'مراجعات حقيقية',

            // Home - Final CTA
            home_cta_title: 'جاهز للعثور على منزلك المثالي؟',
            home_cta_subtitle: 'انضم إلى آلاف الطلاب التونسيين الذين وجدوا بالفعل سكنهم المثالي من خلال UNIDAR. ابدأ بحثك اليوم - إنه مجاني!',
            home_cta_btn: 'ابدأ مجاناً',

            // Footer
            footer_brand_desc: "المنصة الأولى للسكن الطلابي في تونس. مساعدة طلاب الجامعات في العثور على سكن موثوق وزملاء سكن متوافقين منذ عام 2026.",
            footer_platform: 'المنصة',
            footer_for_owners: 'للملاك',
            footer_support: 'الدعم',
            footer_browse: 'تصفح الإعلانات',
            footer_find_roommates: 'البحث عن زملاء سكن',
            footer_list_property: 'إدراج عقار',
            footer_manage: 'إدارة الإعلانات',
            footer_help: 'مركز المساعدة',
            footer_contact: 'اتصل بنا',
            footer_report: 'الإبلاغ عن مشكلة',
            footer_copyright: "© 2026 UNIDAR. جميع الحقوق محفوظة. صنع بـ ❤️ للطلاب التونسيين.",

            // Common actions
            btn_save: 'حفظ',
            btn_cancel: 'إلغاء',
            btn_submit: 'إرسال',
            btn_edit: 'تعديل',
            btn_delete: 'حذف',
            btn_view_details: 'التفاصيل',
            btn_send_message: 'إرسال رسالة',
            btn_apply_filters: 'تطبيق الفلاتر',
            btn_browse_all: 'عرض الكل',
            btn_find_more: 'المزيد',

            // Listings page
            listings_title: 'ابحث عن منزلك المثالي',
            listings_marketplace: 'السوق',
            listings_map_view: 'عرض الخريطة',
            listings_list_view: 'عرض القائمة',
            filter_min_price: 'السعر الأدنى (دينار)',
            filter_max_price: 'السعر الأقصى (دينار)',
            filter_bedrooms: 'غرف النوم',
            filter_any_bedrooms: 'جميع الغرف',
            filter_property_type: 'نوع العقار',
            filter_any_type: 'جميع الأنواع',
            filter_apartment: 'شقة',
            filter_house: 'منزل',
            filter_studio: 'ستوديو',
            filter_shared_room: 'غرفة مشتركة',
            listings_no_results: 'لا توجد إعلانات',
            listings_adjust_filters: 'حاول تعديل الفلاتر',
            listings_per_month: '/شهر',

            // Roommates page
            roommates_title: 'البحث عن زملاء سكن',
            roommates_compatibility: 'التوافق',
            roommates_preferences: 'التفضيلات',
            roommates_no_matches: 'لا توجد تطابقات بعد',
            roommates_set_preferences: 'حدد تفضيلاتك للعثور على زملاء سكن متوافقين',
            roommates_message_btn: 'مراسلة',
            roommates_verified: 'موثق',
            card_generate_contract: 'إنشاء عقد',
            btn_set_preferences: 'تحديد التفضيلات',
            pref_budget: 'الميزانية',
            pref_budget_min: 'الحد الأدنى للميزانية',
            pref_budget_max: 'الحد الأقصى للميزانية',
            pref_cleanliness: 'النظافة',
            pref_cleanliness_level: 'مستوى النظافة',
            pref_no_preference: 'لا تفضيل',
            pref_very_clean: 'نظيف جداً',
            pref_clean: 'نظيف',
            pref_moderate: 'متوسط',
            pref_relaxed: 'مريح',
            pref_smoking: 'التدخين',
            pref_smoking_preference: 'تفضيل التدخين',
            pref_non_smoker: 'غير مدخن',
            pref_smoker: 'مدخن',
            pref_sleep: 'النوم',
            pref_sleep_schedule: 'جدول النوم',
            pref_early_riser: 'يستيقظ باكراً',
            pref_normal: 'عادي',
            pref_night_owl: 'بومة الليل',
            pref_save_refresh: 'حفظ وتحديث النتائج',

            // Dashboard
            dashboard_title: 'لوحة التحكم',
            dashboard_welcome: 'مرحباً بعودتك',
            dashboard_portal: 'البوابة',
            dashboard_saved: 'المحفوظات',
            dashboard_matches: 'التطابقات',
            dashboard_unread: 'غير مقروء',
            dashboard_nearby: 'قريب',
            dashboard_edit_profile: 'تعديل الملف',
            dashboard_saved_listings: 'المحفوظة للاحقاً',
            dashboard_neighborhood: 'مراقبة الحي',
            dashboard_nearby_listings: 'إعلانات في نطاق 10 كم',
            dashboard_matchmaking: 'التطابقات',
            dashboard_conversations: 'المحادثات',
            dashboard_inbox: 'صندوق الوارد',

            // Login/Register
            login_page_title: 'تسجيل الدخول - UNIDAR',
            login_title: 'تسجيل الدخول',
            login_welcome: 'مرحباً بعودتك',
            login_access: 'الوصول إلى حسابك في UNIDAR',
            login_email: 'البريد الإلكتروني',
            login_password: 'كلمة المرور',
            login_forgot: 'نسيت كلمة المرور؟',
            login_signin: 'تسجيل الدخول',
            login_no_account: 'ليس لديك حساب؟',
            login_create: 'إنشاء حساب',
            login_email_placeholder: 'student@university.edu',
            login_password_placeholder: '••••••••',
            login_signing_in: 'جاري تسجيل الدخول...',

            register_title: 'إنشاء حساب',
            register_welcome: 'انضم إلينا',
            register_join: 'انضم إلى UNIDAR',
            register_start: 'ابدأ رحلتك مع UNIDAR',
            register_fullname: 'الاسم الكامل',
            register_fullname_placeholder: 'فلان الفلاني',
            register_email: 'البريد الإلكتروني',
            register_email_placeholder: 'student@university.edu',
            register_email_hint: 'البريد الإلكتروني الجامعي يساعد في الثقة والتحقق.',
            register_password: 'كلمة المرور',
            register_password_placeholder: '8 أحرف على الأقل',
            register_confirm: 'تأكيد كلمة المرور',
            register_confirm_placeholder: 'أعد إدخال كلمة المرور',
            register_phone: 'رقم الهاتف (اختياري)',
            register_phone_placeholder: '+216 ...',
            register_role: 'أنا',
            register_role_select: 'اختر...',
            register_student: 'طالب',
            register_owner: 'مالك عقار',
            register_university: 'اسم الجامعة',
            register_university_placeholder: 'مثلاً: ESPRIT ، INSAT ، MSB...',
            register_map_radius: 'نطاق البحث (المنطقة المفضلة)',
            register_map_hint: 'انقر على الخريطة لتحديد منطقة دراستك.',
            register_selected_location: 'المختار:',
            register_agree: 'أوافق على شروط الخدمة',
            register_create: 'إنشاء حساب',
            register_creating_account: 'جاري إنشاء الحساب...',
            register_have_account: 'لديك حساب بالفعل؟',
            register_signin: 'تسجيل الدخول',

            // Listing detail
            detail_back: 'العودة للإعلانات',
            detail_bedrooms: 'غرف النوم',
            detail_bathrooms: 'الحمامات',
            detail_size: 'المساحة',
            detail_type: 'النوع',
            detail_description: 'الوصف',
            detail_amenities: 'المرافق',
            detail_location: 'الموقع',
            detail_contact_owner: 'تواصل مع المالك',
            detail_save_listing: 'حفظ الإعلان',
            detail_saved: 'محفوظ',

            // Owner listings
            owner_my_properties: 'عقاراتي',
            owner_add_property: 'إضافة عقار',
            owner_no_properties: 'لا توجد عقارات بعد',
            owner_create_first: 'أنشئ إعلانك الأول',
            owner_total_properties: 'إجمالي العقارات',
            owner_active_listings: 'الإعلانات النشطة',
            owner_total_inquiries: 'إجمالي الاستفسارات',
            owner_rented_properties: 'عقارات مؤجرة',
            owner_management: 'الإدارة',
            owner_existing_properties: 'العقارات الموجودة',
            owner_refresh_list: 'تحديث القائمة',
            owner_add_new_property: 'إضافة عقار جديد',
            owner_title_label: 'العنوان',
            owner_description_label: 'الوصف',
            owner_address_label: 'العنوان بالتفصيل',
            owner_price_label: 'السعر شهرياً (دينار)',
            owner_type_label: 'نوع العقار',
            owner_bedrooms_label: 'غرف النوم',
            owner_bathrooms_label: 'الحمامات',
            owner_available_label: 'متاح من',
            owner_loading_properties: 'جاري تحميل عقاراتك...',

            // Verification
            verification_title: 'التحقق من الطالب',
            verification_upload: 'ارفع بطاقة الطالب',
            verification_pending: 'التحقق قيد الانتظار',
            verification_approved: 'طالب موثق',

            // Footer
            footer_tagline: 'سكن طلابي آمن',
            footer_rights: 'جميع الحقوق محفوظة',

            // Chat Widget
            chat_messages: 'الرسائل',
            chat_active: 'النشطة',
            chat_archived: 'المؤرشفة',
            chat_no_messages: 'لا توجد رسائل.',
            chat_type_message: 'اكتب رسالة...',
            chat_send: 'إرسال',
            chat_archive: 'أرشفة',
            chat_unarchive: 'إلغاء الأرشفة',
            chat_delete: 'حذف',
            chat_block: 'حظر المستخدم',
            chat_report: 'إبلاغ',
            chat_loading: 'جاري تحميل المحادثات...',

            // Listing Cards & Detail
            card_bedrooms: 'غرف نوم',
            card_bathrooms: 'حمامات',
            card_bath: 'حمام',
            card_per_month: '/شهر',
            card_details: 'التفاصيل',
            card_no_listings: 'لا توجد إعلانات',
            card_adjust_filters: 'حاول تعديل الفلاتر',
            card_save: 'حفظ في المفضلة',
            card_saved: 'محفوظ',
            card_contact: 'تواصل مع المالك',
            card_about: 'عن هذا المكان',
            card_available: 'متاح',
            card_available_from: 'متاح من',
            card_location: 'الموقع',
            card_share: 'مشاركة',
            card_report: 'الإبلاغ عن مشكلة في هذا الإعلان',
            card_per_month_full: '/ شهر',
            card_no_description: 'لا يوجد وصف.',

            // Misc
            owner_property_portfolio: 'محفظة العقارات',
            owner_portfolio_desc: 'التوزيع الجغرافي لعقاراتك',
            loading: 'جاري التحميل...',
            error_occurred: 'حدث خطأ',
            verified: 'موثق',
            student: 'طالب',

            // Dynamic Listings Content
            bedrooms_short: 'سرير',
            bedrooms_abbr: 'غ.ن.',
            places: 'أماكن',
            places_left: 'مكان (أماكن) متبقي',
            full_badge: 'ممتلئ 👥',
            discover_offer: 'اكتشف هذا العرض ←',
            view_details_arrow: 'عرض التفاصيل ←',
            loading_marketplace: 'جاري تحميل السوق...',
            failed_load: 'فشل تحميل الإعلانات. يرجى المحاولة مرة أخرى.',
            role_student: 'طالب',

            // View Toggle
            map_view: 'عرض الخريطة',
            list_view: 'عرض القائمة',

            // Filter Placeholders
            placeholder_min_price: 'مثلاً 400',
            placeholder_max_price: 'مثلاً 1200',

            // Footer Links
            footer_about: 'عن',
            footer_help_link: 'مساعدة',
            footer_privacy: 'الخصوصية',
            footer_terms: 'الشروط',

            // Common UI
            explore_listings_text: 'استكشف خيارات السكن الطلابي عالية الجودة والمعتمدة لسلامتك وراحتك.',
            per_month: '/شهر',
            month_abbr: '/شهر',
            sqm: 'م²',

            // Bedroom Options
            bedroom_1: 'غرفة نوم واحدة',
            bedroom_2: 'غرفتا نوم',
            bedroom_3: '3 غرف نوم',
            bedroom_4: '4+ غرف نوم',

            // Map Popup
            view_details_link: 'عرض التفاصيل'
        }
    },

    /**
     * Initialize the language system
     */
    init: function () {
        // Get saved language or default to English
        var savedLang = localStorage.getItem('unidar_language');
        this.currentLang = savedLang && this.languages[savedLang] ? savedLang : 'en';
        this.applyLanguage();
    },

    /**
     * Get translation for a key
     */
    t: function (key) {
        var translations = this.translations[this.currentLang];
        if (translations && translations[key]) {
            return translations[key];
        }
        // Fallback to English
        if (this.translations.en && this.translations.en[key]) {
            return this.translations.en[key];
        }
        // Return key if no translation found
        return key;
    },

    /**
     * Set the current language
     */
    setLanguage: function (lang) {
        if (!this.languages[lang]) {
            console.warn('Language not supported:', lang);
            return;
        }
        this.currentLang = lang;
        localStorage.setItem('unidar_language', lang);
        this.applyLanguage();
    },

    /**
     * Get the current language code
     */
    getLanguage: function () {
        return this.currentLang;
    },

    /**
     * Apply language to the page
     */
    applyLanguage: function () {
        var lang = this.currentLang;
        var langInfo = this.languages[lang];

        // Set document direction and lang attribute
        document.documentElement.setAttribute('lang', lang);
        document.documentElement.setAttribute('dir', langInfo.dir);

        // Translate all elements with data-i18n attribute
        this.translatePage();

        // Update language switcher display if exists
        this.updateSwitcherDisplay();
    },

    /**
     * Translate all elements with data-i18n attribute
     */
    translatePage: function () {
        var self = this;
        var elements = document.querySelectorAll('[data-i18n]');

        elements.forEach(function (el) {
            var key = el.getAttribute('data-i18n');
            var translation = self.t(key);

            // Check if it's an input placeholder
            if (el.hasAttribute('placeholder') && el.getAttribute('data-i18n-placeholder')) {
                el.placeholder = self.t(el.getAttribute('data-i18n-placeholder'));
            }

            // Check for data-i18n-title
            if (el.hasAttribute('data-i18n-title')) {
                el.title = self.t(el.getAttribute('data-i18n-title'));
            }

            // Set text content (but not for inputs)
            if (el.tagName !== 'INPUT' && el.tagName !== 'TEXTAREA') {
                el.textContent = translation;
            }
        });

        // Handle placeholder translations separately
        var placeholderElements = document.querySelectorAll('[data-i18n-placeholder]');
        placeholderElements.forEach(function (el) {
            var key = el.getAttribute('data-i18n-placeholder');
            el.placeholder = self.t(key);
        });
    },

    /**
     * Update the language switcher display
     */
    updateSwitcherDisplay: function () {
        var langInfo = this.languages[this.currentLang];
        var switchers = document.querySelectorAll('.lang-switcher-current');

        switchers.forEach(function (el) {
            el.textContent = langInfo.flag + ' ' + langInfo.nativeName.substring(0, 2).toUpperCase();
        });
    },

    /**
     * Create language switcher HTML
     */
    createSwitcherHTML: function () {
        var self = this;
        var currentLangInfo = this.languages[this.currentLang];

        var html = '<div class="lang-switcher">' +
            '<button class="lang-switcher-btn" type="button" aria-label="Change language">' +
            '<span class="lang-switcher-current">' + currentLangInfo.flag + ' ' + currentLangInfo.nativeName.substring(0, 2).toUpperCase() + '</span>' +
            '<span class="lang-switcher-arrow">▼</span>' +
            '</button>' +
            '<div class="lang-dropdown">';

        Object.keys(this.languages).forEach(function (code) {
            var lang = self.languages[code];
            var activeClass = code === self.currentLang ? ' lang-option-active' : '';
            html += '<button class="lang-option' + activeClass + '" data-lang="' + code + '" type="button">' +
                '<span class="lang-flag">' + lang.flag + '</span>' +
                '<span class="lang-name">' + lang.nativeName + '</span>' +
                '</button>';
        });

        html += '</div></div>';

        return html;
    },

    /**
     * Inject language switcher into navbar
     */
    injectSwitcher: function (containerSelector) {
        var self = this;
        var containers = document.querySelectorAll(containerSelector || '.nav-container');

        containers.forEach(function (container) {
            // Check if switcher already exists
            if (container.querySelector('.lang-switcher')) return;

            // Find the right place to inject (before logout button or at the end)
            var actionsDiv = container.querySelector('div[style*="display: flex"]') ||
                container.querySelector('.header-actions');

            if (actionsDiv) {
                var switcherHTML = self.createSwitcherHTML();
                actionsDiv.insertAdjacentHTML('afterbegin', switcherHTML);
            } else {
                // Append to container if no actions div found
                var switcherHTML = self.createSwitcherHTML();
                container.insertAdjacentHTML('beforeend', '<div style="display: flex; align-items: center; gap: var(--space-md);">' + switcherHTML + '</div>');
            }

            // Attach event listeners
            self.attachSwitcherEvents(container);
        });
    },

    /**
     * Attach event listeners to language switcher
     */
    attachSwitcherEvents: function (container) {
        var self = this;
        var switcher = container.querySelector('.lang-switcher');
        if (!switcher) return;

        var btn = switcher.querySelector('.lang-switcher-btn');
        var dropdown = switcher.querySelector('.lang-dropdown');

        // Toggle dropdown on button click
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            switcher.classList.toggle('open');
        });

        // Handle language selection
        var options = dropdown.querySelectorAll('.lang-option');
        options.forEach(function (option) {
            option.addEventListener('click', function (e) {
                e.stopPropagation();
                var lang = this.getAttribute('data-lang');
                self.setLanguage(lang);
                switcher.classList.remove('open');
            });
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function () {
            switcher.classList.remove('open');
        });
    }
};

// Make it globally accessible
window.UNIDAR_I18N = UNIDAR_I18N;

// Auto-initialize and inject switcher when DOM is ready
function initI18N() {
    UNIDAR_I18N.init();
    // Auto-inject language switcher into all navbars
    UNIDAR_I18N.injectSwitcher('.nav-container');
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initI18N);
} else {
    initI18N();
}
