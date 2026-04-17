<?php

namespace App\Service;

/**
 * Regex-based voice intent classifier.
 *
 * Ports the offline fallback logic from the Java desktop client
 * (ClaudeClient.fallbackClassify) and extends it with web-only intents.
 *
 * Supports English, French and (transliterated + native script) Arabic.
 * No external API calls — runs 100% locally.
 *
 * @phpstan-type IntentResult array{intent:string, params:array<string,mixed>, reply:string, lang:string}
 */
class VoiceIntentClassifier
{
    /**
     * @param array<string,mixed> $pageContext Optional context from the current page.
     *   Keys: page, itemCount, userRole, currentMode, focusedField, visibleButtons, visibleInputs
     * @return array{intent:string, params:array<string,mixed>, reply:string, lang:string}
     */
    public function classify(string $text, string $currentRoute = '', string $locale = 'en', array $pageContext = []): array
    {
        $raw   = trim($text);
        $input = mb_strtolower($raw);
        $lang  = $this->detectLang($input, $locale);

        // ── CONFIRM / DENY (short words — must come early to avoid false matches) ──

        if (preg_match('/^(?:yes|oui|نعم|yep|yeah|sure|confirm|proceed|ok|okay|go|do\s+it|continue|confirmer|d\'accord|تأكيد|نعم\s+بالطبع)$/u', $input)) {
            return $this->reply('CONFIRM_YES', [], $lang,
                'Confirmed.',
                'Confirmé.',
                'تم التأكيد.');
        }

        if (preg_match('/^(?:no|non|لا|nope|cancel|abort|stop|never\s+mind|annuler|إلغاء)$/u', $input)) {
            return $this->reply('CONFIRM_NO', [], $lang,
                'Cancelled.',
                'Annulé.',
                'تم الإلغاء.');
        }

        // ── FORM SUBMIT / CLEAR ──

        if (preg_match('/(?:submit(?:\s+(?:the\s+)?form)?|save\s+form|send\s+form|publish|envoyer|soumettre|إرسال\s+النموذج|تقديم)/u', $input)) {
            return $this->reply('FORM_SUBMIT', [], $lang,
                'Submitting the form.',
                'Envoi du formulaire.',
                'جاري إرسال النموذج.');
        }

        if (preg_match('/(?:(?:clear|reset|erase)\s+(?:the\s+)?form|réinitialiser|effacer\s+le\s+formulaire|مسح\s+النموذج)/u', $input)) {
            return $this->reply('FORM_CLEAR', [], $lang,
                'Form cleared.',
                'Formulaire réinitialisé.',
                'تم مسح النموذج.');
        }

        // ── FIELD OPERATIONS ──

        // FILL_FIELD — "fill [field] with [value]" / "type [value] into [field]"
        // Pattern A: fill/set/remplis/اكتب <field> with/to/avec/في <value>
        if (preg_match('/(?:fill|set|remplis|mets|اكتب|ضع)\s+(?:the\s+)?(.+?)\s+(?:with|to|=|à|avec|في|ب)\s+(.+)$/ui', $raw, $m)) {
            return $this->reply('FILL_FIELD', ['field' => trim($m[1]), 'value' => trim($m[2])], $lang,
                "Filling {$m[1]} with {$m[2]}.",
                "Remplissage du champ {$m[1]} avec {$m[2]}.",
                "جاري ملء {$m[1]} بـ {$m[2]}.");
        }
        // Pattern B: type/write/tapez/أكتب <value> in/into/dans/في <field>
        if (preg_match('/(?:type|write|tapez|أكتب)\s+(.+?)\s+(?:in(?:to)?|dans|في)\s+(?:the\s+)?(.+?)(?:\s+field)?$/ui', $raw, $m)) {
            return $this->reply('FILL_FIELD', ['field' => trim($m[2]), 'value' => trim($m[1])], $lang,
                "Typing {$m[1]} into {$m[2]}.",
                "Saisie de {$m[1]} dans {$m[2]}.",
                "جاري كتابة {$m[1]} في {$m[2]}.");
        }

        // READ_FIELD — "what's in [field]" / "read [field] field"
        if (preg_match('/(?:read|what(?:\'s|\s+is)\s+in|lis)\s+(?:the\s+)?(.+?)(?:\s+field)?$/ui', $raw, $m)) {
            return $this->reply('READ_FIELD', ['field' => trim($m[1])], $lang,
                "Reading field {$m[1]}.",
                "Lecture du champ {$m[1]}.",
                "قراءة حقل {$m[1]}.");
        }

        // CLEAR_FIELD — "clear [field]" / "erase [field]"
        if (preg_match('/(?:clear|erase|empty|delete|vide|efface|امسح|احذف)\s+(?:the\s+)?(.+?)(?:\s+field)?$/ui', $raw, $m)) {
            return $this->reply('CLEAR_FIELD', ['field' => trim($m[1])], $lang,
                "Clearing field {$m[1]}.",
                "Effacement du champ {$m[1]}.",
                "مسح حقل {$m[1]}.");
        }

        // ── DICTATION ──

        if (preg_match('/(?:start\s+(?:dictation|dictée)|voice\s+input|type\s+mode|start\s+typing|commencer\s+la\s+dictée|^dictée$|ابدأ\s+الإملاء|وضع\s+الكتابة)/u', $input)) {
            return $this->reply('DICTATION_START', [], $lang,
                'Dictation mode on. Speak to type.',
                'Mode dictée activé. Parlez pour écrire.',
                'وضع الإملاء نشط. تحدث للكتابة.');
        }

        if (preg_match('/(?:stop\s+dictation|exit\s+dictation|done\s+typing|arrêter\s+(?:la\s+)?dictée|stop\s+dictée|إيقاف\s+الإملاء|^خروج$)/u', $input)) {
            return $this->reply('DICTATION_STOP', [], $lang,
                'Dictation stopped.',
                'Dictée arrêtée.',
                'تم إيقاف الإملاء.');
        }

        // ── PICK OPTION (dropdown selection by voice) ──

        if (preg_match('/(?:choose|pick|option|select\s+option|choisir|choisis|option\s+numéro|option\s+numero|اختر|اختار|خيار)\s+(.+)$/ui', $raw, $m)) {
            return $this->reply('PICK_OPTION', ['option' => trim($m[1])], $lang,
                "Picking option: {$m[1]}.",
                "Choix de l'option : {$m[1]}.",
                "اختيار الخيار: {$m[1]}.");
        }

        // ── FIELD NAVIGATION ──

        if (preg_match('/(?:next\s+(?:field|input)|^tab$|champ\s+suivant|الحقل\s+التالي)/u', $input)) {
            return $this->reply('NEXT_FIELD', [], $lang,
                'Moving to next field.',
                'Champ suivant.',
                'الانتقال للحقل التالي.');
        }

        if (preg_match('/(?:previous\s+(?:field|input)|back\s+field|champ\s+précédent|champ\s+precedent|الحقل\s+السابق)/u', $input)) {
            return $this->reply('PREV_FIELD', [], $lang,
                'Moving to previous field.',
                'Champ précédent.',
                'الانتقال للحقل السابق.');
        }

        // ── SELECT / CLICK MODE ──

        if (preg_match('/(?:select\s+mode|click\s+mode|number\s+elements|show\s+numbers|mode\s+sélection|mode\s+selection|afficher\s+numéros|afficher\s+numeros|وضع\s+التحديد)/u', $input)) {
            return $this->reply('SELECT_MODE_ON', [], $lang,
                'Select mode on. Say a number to click.',
                'Mode sélection activé. Dites un numéro.',
                'وضع التحديد نشط. قل رقماً للنقر.');
        }

        if (preg_match('/(?:exit\s+select\s+mode|close\s+overlay|hide\s+numbers|quitter\s+sélection|quitter\s+selection|إخفاء\s+التحديد)/u', $input)) {
            return $this->reply('SELECT_MODE_OFF', [], $lang,
                'Select mode off.',
                'Mode sélection désactivé.',
                'تم إخفاء التحديد.');
        }

        // CLICK_N — "click 3", "number 5", "element 2" — numeric only, NOT ordinal words
        if (preg_match('/(?:click|number|numéro|numero|select|element|رقم)\s+(\d+)/ui', $input, $m)) {
            $n = (int)$m[1];
            return $this->reply('CLICK_N', ['n' => $n], $lang,
                "Clicking element $n.",
                "Clic sur l'élément $n.",
                "النقر على العنصر $n.");
        }

        // CLICK_ELEMENT — "click [label]", "press [label]", etc.
        if (preg_match('/(?:click(?:\s+on)?|press|tap|cliquer?\s+(?:sur)?|appuyer?\s+(?:sur)?|انقر(?:\s+على)?|اضغط(?:\s+على)?)\s+(?:the\s+|on\s+)?(.+)$/ui', $raw, $m)) {
            return $this->reply('CLICK_ELEMENT', ['label' => trim($m[1])], $lang,
                "Clicking \"{$m[1]}\".",
                "Clic sur \"{$m[1]}\".",
                "النقر على \"{$m[1]}\".");
        }

        // ── SMART QUERY ──

        if (preg_match('/(?:how\s+many|^count$|combien|كم\s+عدد)/u', $input)) {
            $count = $pageContext['itemCount'] ?? null;
            if ($count !== null) {
                $n = (int)$count;
                return $this->reply('COUNT_ITEMS', ['itemCount' => $n], $lang,
                    "There are $n items on this page.",
                    "Il y a $n éléments sur cette page.",
                    "يوجد $n عنصر في هذه الصفحة.");
            }
            return $this->reply('COUNT_ITEMS', [], $lang,
                'I can count the visible items for you.',
                'Je peux compter les éléments visibles.',
                'يمكنني عدّ العناصر المرئية.');
        }

        if (preg_match('/(?:what\s+page\s+is\s+this|where\s+am\s+i|describe\s+(?:the\s+)?page|quelle\s+page|où\s+suis[- ]je|ou\s+suis[- ]je|ما\s+هذه\s+الصفحة)/u', $input)) {
            return $this->reply('WHAT_PAGE', [], $lang,
                "You are on the current page.",
                "Vous êtes sur la page actuelle.",
                "أنت على الصفحة الحالية.");
        }

        if (preg_match('/(?:what\s+can\s+i\s+say|available\s+commands|list\s+commands|commandes\s+disponibles|ماذا\s+أقول)/u', $input)) {
            return $this->reply('WHAT_COMMANDS', [], $lang,
                "You can say: navigate, search, filter, fill field, submit, go back, help…",
                "Vous pouvez dire : naviguer, chercher, filtrer, remplir un champ, envoyer…",
                "يمكنك قول: تصفح، ابحث، رشّح، ملء حقل، إرسال، رجوع…");
        }

        // ── APP ACTIONS ──

        if (preg_match('/(?:generate\s+contract|create\s+contract|make\s+contract|générer\s+(?:le\s+)?contrat|generer\s+(?:le\s+)?contrat|إنشاء\s+عقد)/u', $input)) {
            return $this->reply('GENERATE_CONTRACT', [], $lang,
                'Generating contract.',
                'Génération du contrat.',
                'جاري إنشاء العقد.');
        }

        if (preg_match('/(?:send\s+(?:a\s+)?message|contact\s+(?:the\s+)?owner|message\s+(?:them|landlord)|envoyer\s+(?:un\s+)?message|إرسال\s+رسالة)/u', $input)) {
            return $this->reply('SEND_MESSAGE', [], $lang,
                'Opening message.',
                'Ouverture du message.',
                'فتح الرسالة.');
        }

        if (preg_match('/(?:save\s+listing|bookmark\s+this|add\s+to\s+saved|favoris|sauvegarder|حفظ\s+الإعلان)/u', $input)) {
            return $this->reply('SAVE_LISTING', [], $lang,
                'Listing saved.',
                'Annonce sauvegardée.',
                'تم حفظ الإعلان.');
        }

        if (preg_match('/(?:add\s+(?:a\s+)?listing|new\s+property|create\s+listing|add\s+property|ajouter\s+(?:une\s+)?annonce|إضافة\s+إعلان)/u', $input)) {
            return $this->reply('NEW_LISTING', [], $lang,
                'Creating a new listing.',
                'Création d\'une nouvelle annonce.',
                'إنشاء إعلان جديد.');
        }

        // ── PARAMETER-EXTRACTING INTENTS ──

        // SEARCH — "search for X" / "chercher X" / "ابحث عن X"
        if (preg_match('/(?:search(?:\s+for)?|find(?:\s+me)?|look\s+for|chercher?|recherche[rz]?|trouve[rz]?(?:\s+moi)?|ابحث(?:\s*عن)?|دور(?:\s*على)?)\s+(.+)$/u', $input, $m)) {
            $query = trim($m[1]);
            return $this->reply('SEARCH', ['query' => $query], $lang,
                "Searching for $query.",
                "Recherche de $query.",
                "جاري البحث عن $query.");
        }

        // FILTER_LISTINGS — "under X", "less than X", "cheaper than X"
        if (preg_match('/(?:under|less\s+than|cheaper\s+than|below|moins\s+de|en\s+dessous\s+de|أقل\s+من|اقل\s+من)\s+(\d+)/u', $input, $m)) {
            return $this->reply('FILTER_LISTINGS', ['price_max' => (int)$m[1]], $lang,
                "Filtering listings under {$m[1]}.",
                "Filtrage des logements sous {$m[1]}.",
                "تصفية الإعلانات تحت {$m[1]}.");
        }
        if (preg_match('/(?:over|more\s+than|above|plus\s+de|au\s+dessus\s+de|أكثر\s+من|اكثر\s+من)\s+(\d+)/u', $input, $m)) {
            return $this->reply('FILTER_LISTINGS', ['price_min' => (int)$m[1]], $lang,
                "Filtering listings over {$m[1]}.",
                "Filtrage des logements au-dessus de {$m[1]}.",
                "تصفية الإعلانات فوق {$m[1]}.");
        }

        // OPEN_LISTING_N — "open the third one" / "ouvre le troisième"
        $ordinals = [
            'first'      => 1, 'second'     => 2, 'third'      => 3, 'fourth'     => 4, 'fifth'      => 5,
            'premier'    => 1, 'deuxième'   => 2, 'deuxieme'   => 2, 'troisième'  => 3, 'troisieme'  => 3,
            'quatrième'  => 4, 'quatrieme'  => 4, 'cinquième'  => 5, 'cinquieme'  => 5,
            'الأول'      => 1, 'الاول'      => 1, 'الثاني'     => 2, 'الثالث'     => 3,
            'الرابع'     => 4, 'الخامس'     => 5,
        ];
        foreach ($ordinals as $word => $n) {
            if (str_contains($input, $word)) {
                return $this->reply('OPEN_LISTING_N', ['n' => $n], $lang,
                    "Opening result #$n.",
                    "Ouverture du résultat n°$n.",
                    "فتح النتيجة رقم $n.");
            }
        }
        if (preg_match('/(?:open|ouvr[ei]z?|افتح)\s+(?:number\s+|numero\s+|n°\s*|#\s*|رقم\s+)?(\d+)/u', $input, $m)) {
            return $this->reply('OPEN_LISTING_N', ['n' => (int)$m[1]], $lang,
                "Opening result #{$m[1]}.",
                "Ouverture du résultat n°{$m[1]}.",
                "فتح النتيجة رقم {$m[1]}.");
        }

        // SWITCH_LANGUAGE
        if (preg_match('/(?:switch|change|passe[rz]?|set).{0,20}(english|anglais|français|francais|french|arab(?:ic|e)?|عرب)/u', $input, $m)) {
            $langCode = match (true) {
                str_contains($m[1], 'engl') || str_contains($m[1], 'angl') => 'en',
                str_contains($m[1], 'franc') || str_contains($m[1], 'fran') || str_contains($m[1], 'fren') => 'fr',
                default => 'ar',
            };
            return $this->reply('SWITCH_LANGUAGE', ['lang' => $langCode], $lang,
                "Switching to $langCode.",
                "Passage en $langCode.",
                "التبديل إلى $langCode.");
        }

        // ── KEYWORD → INTENT TABLE (order matters: more specific first) ──
        $rules = [
            'NAVIGATE_SAVED'        => ['saved listing', 'saved', 'favoris', 'favori', 'sauvegard', 'المحفوظ'],
            'NAVIGATE_MY_LISTINGS'  => ['my listing', 'my annonce', 'mes annonce', 'mes logement', 'إعلاناتي', 'اعلاناتي'],
            'NAVIGATE_LISTINGS'     => ['listing', 'housing', 'apartment', 'logement', 'annonce', 'appartement', 'إعلان', 'سكن', 'شقة'],
            'NAVIGATE_ROOMMATES'    => ['roommate', 'coloc', 'شريك', 'زميل سكن'],
            'NAVIGATE_MESSAGES'     => ['message', 'chat', 'inbox', 'conversation', 'messagerie', 'رسائل', 'محادث'],
            'NAVIGATE_CONTRACTS'    => ['contract', 'contrat', 'lease', 'bail', 'عقد'],
            'NAVIGATE_DASHBOARD'    => ['dashboard', 'tableau de bord', 'لوحة', 'لوحة التحكم'],
            'NAVIGATE_PREMIUM'      => ['premium', 'subscription', 'subscribe', 'abonnement', 'abonne', 'اشتراك'],
            'NAVIGATE_VERIFICATION' => ['verification', 'verify', 'vérif', 'verif', 'توثيق', 'تحقق'],
            'NAVIGATE_HOME'         => ['home', 'homepage', 'accueil', 'الرئيسية', 'الصفحة الرئيسية'],
            'REPORT'                => ['report', 'complain', 'reclamation', 'réclamation', 'plainte', 'شكوى', 'بلاغ'],
            'LOGIN'                 => ['log in', 'login', 'sign in', 'signin', 'connecte', 'connecter', 'تسجيل الدخول', 'دخول'],
            'REGISTER'              => ['register', 'sign up', 'signup', 'inscri', 'créer un compte', 'creer un compte', 'إنشاء حساب', 'تسجيل'],
            'LOGOUT'                => ['log out', 'logout', 'sign out', 'déconnect', 'deconnect', 'تسجيل الخروج', 'خروج'],
            'READ_PAGE'             => ['read this', 'read page', 'read it', 'lis la page', 'lis moi', 'اقرأ', 'اقرا'],
            'REFRESH'               => ['refresh', 'reload', 'actualise', 'recharg', 'تحديث'],
            'GO_BACK'               => ['go back', 'back', 'retour', 'retourne', 'précéd', 'preced', 'رجوع', 'ارجع'],
            'HELP'                  => ['help', 'aide', 'aidez', 'مساعدة', 'ساعدني', 'commands', 'commandes'],
            'SCROLL_DOWN'           => ['scroll down', 'scroll', 'descend', 'descendre', 'vers le bas', 'انزل', 'أسفل'],
            'SCROLL_UP'             => ['scroll up', 'monte', 'remonte', 'vers le haut', 'اصعد', 'أعلى'],
        ];

        foreach ($rules as $intent => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($input, $kw)) {
                    return $this->replyForIntent($intent, $lang);
                }
            }
        }

        // ── DICTATE_TEXT fallback ──
        // If we are in dictation mode and no intent matched, treat the utterance as typed text.
        if (isset($pageContext['currentMode']) && $pageContext['currentMode'] === 'dictation') {
            $preview = mb_substr($raw, 0, 30);
            return $this->reply('DICTATE_TEXT', ['text' => $raw], $lang,
                "Typed: $preview",
                "Tapé : $preview",
                "تم كتابة: $preview");
        }

        return $this->reply('UNKNOWN', [], $lang,
            "Sorry, I didn't catch that. Say 'help' for a list of commands.",
            "Désolé, je n'ai pas compris. Dites « aide » pour la liste des commandes.",
            "عذراً، لم أفهم. قل «مساعدة» لعرض الأوامر."
        );
    }

    private function replyForIntent(string $intent, string $lang): array
    {
        $messages = [
            // ── Navigation ──
            'NAVIGATE_HOME'         => ['Going home',                          'Retour à l\'accueil',               'العودة إلى الرئيسية'],
            'NAVIGATE_LISTINGS'     => ['Opening listings',                    'Ouverture des logements',           'فتح الإعلانات'],
            'NAVIGATE_MY_LISTINGS'  => ['Opening my listings',                 'Ouverture de mes annonces',         'فتح إعلاناتي'],
            'NAVIGATE_SAVED'        => ['Opening saved listings',              'Ouverture des favoris',             'فتح المحفوظات'],
            'NAVIGATE_ROOMMATES'    => ['Finding roommates',                   'Recherche de colocataires',         'البحث عن شريك سكن'],
            'NAVIGATE_MESSAGES'     => ['Opening messages',                    'Ouverture de la messagerie',        'فتح الرسائل'],
            'NAVIGATE_CONTRACTS'    => ['Opening contracts',                   'Ouverture des contrats',            'فتح العقود'],
            'NAVIGATE_DASHBOARD'    => ['Opening dashboard',                   'Ouverture du tableau de bord',      'فتح لوحة التحكم'],
            'NAVIGATE_PREMIUM'      => ['Opening premium',                     'Ouverture de l\'abonnement',        'فتح الاشتراك'],
            'NAVIGATE_VERIFICATION' => ['Opening verification',                'Ouverture de la vérification',      'فتح التحقق'],
            // ── Auth ──
            'REPORT'                => ['Opening a report',                    'Ouverture d\'une réclamation',      'فتح بلاغ'],
            'LOGIN'                 => ['Opening login',                       'Ouverture de la connexion',         'فتح تسجيل الدخول'],
            'REGISTER'              => ['Opening registration',                'Ouverture de l\'inscription',       'فتح التسجيل'],
            'LOGOUT'                => ['Logging you out',                     'Déconnexion',                       'جاري الخروج'],
            // ── Page actions ──
            'READ_PAGE'             => ['Reading the page',                    'Lecture de la page',                'قراءة الصفحة'],
            'REFRESH'               => ['Refreshing',                          'Actualisation',                     'جاري التحديث'],
            'GO_BACK'               => ['Going back',                          'Retour en arrière',                 'العودة للخلف'],
            'HELP'                  => ['Here is what I can do',               'Voici ce que je peux faire',        'هذا ما يمكنني فعله'],
            'SCROLL_DOWN'           => ['Scrolling down',                      'Défilement vers le bas',            'تمرير للأسفل'],
            'SCROLL_UP'             => ['Scrolling up',                        'Défilement vers le haut',           'تمرير للأعلى'],
            // ── Form / field ──
            'FORM_SUBMIT'           => ['Submitting the form.',                'Envoi du formulaire.',              'جاري إرسال النموذج.'],
            'FORM_CLEAR'            => ['Form cleared.',                       'Formulaire réinitialisé.',          'تم مسح النموذج.'],
            'NEXT_FIELD'            => ['Moving to next field.',               'Champ suivant.',                    'الانتقال للحقل التالي.'],
            'PREV_FIELD'            => ['Moving to previous field.',           'Champ précédent.',                  'الانتقال للحقل السابق.'],
            // ── Dictation ──
            'DICTATION_START'       => ['Dictation mode on.',                  'Mode dictée activé.',               'وضع الإملاء نشط.'],
            'DICTATION_STOP'        => ['Dictation stopped.',                  'Dictée arrêtée.',                   'تم إيقاف الإملاء.'],
            'DICTATE_TEXT'          => ['Text captured.',                      'Texte capturé.',                    'تم التقاط النص.'],
            'PICK_OPTION'           => ['Option selected.',                    'Option sélectionnée.',              'تم اختيار الخيار.'],
            // ── Select / click ──
            'SELECT_MODE_ON'        => ['Select mode on.',                     'Mode sélection activé.',            'وضع التحديد نشط.'],
            'SELECT_MODE_OFF'       => ['Select mode off.',                    'Mode sélection désactivé.',         'تم إخفاء التحديد.'],
            // ── Smart queries ──
            'COUNT_ITEMS'           => ['I can count the visible items.',      'Je peux compter les éléments.',     'يمكنني عدّ العناصر.'],
            'WHAT_PAGE'             => ['You are on the current page.',        'Vous êtes sur la page actuelle.',   'أنت على الصفحة الحالية.'],
            'WHAT_COMMANDS'         => ['You can say: navigate, search…',      'Vous pouvez dire : naviguer…',      'يمكنك قول: تصفح، ابحث…'],
            // ── Confirm/deny ──
            'CONFIRM_YES'           => ['Confirmed.',                          'Confirmé.',                         'تم التأكيد.'],
            'CONFIRM_NO'            => ['Cancelled.',                          'Annulé.',                           'تم الإلغاء.'],
            // ── App actions ──
            'GENERATE_CONTRACT'     => ['Generating contract.',                'Génération du contrat.',            'جاري إنشاء العقد.'],
            'SEND_MESSAGE'          => ['Opening message.',                    'Ouverture du message.',             'فتح الرسالة.'],
            'SAVE_LISTING'          => ['Listing saved.',                      'Annonce sauvegardée.',              'تم حفظ الإعلان.'],
            'NEW_LISTING'           => ['Creating a new listing.',             'Création d\'une nouvelle annonce.', 'إنشاء إعلان جديد.'],
        ];
        $m = $messages[$intent] ?? ['OK', 'OK', 'حسناً'];
        return $this->reply($intent, [], $lang, $m[0], $m[1], $m[2]);
    }

    private function reply(string $intent, array $params, string $lang, string $en, string $fr, string $ar): array
    {
        $reply = match ($lang) {
            'fr'    => $fr,
            'ar'    => $ar,
            default => $en,
        };
        return ['intent' => $intent, 'params' => $params, 'reply' => $reply, 'lang' => $lang];
    }

    private function detectLang(string $input, string $fallback): string
    {
        // Arabic script present?
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $input)) {
            return 'ar';
        }
        // French markers
        $frMarkers = [
            'bonjour', 'ouvr', 'accueil', 'logement', 'annonce', 'retourne', 'aide',
            'cherch', 'trouv', 'passe', 'déconn', 'inscri', 'connecte', 'actualise',
            'favoris', 'colocataire', 'champ', 'formulaire', 'remplis', 'effacer',
            'soumettre', 'envoyer', 'dictée', 'numéro', 'sélection', 'cliquer', 'appuyer',
        ];
        foreach ($frMarkers as $m) {
            if (str_contains($input, $m)) {
                return 'fr';
            }
        }
        return in_array($fallback, ['en', 'fr', 'ar'], true) ? $fallback : 'en';
    }
}
