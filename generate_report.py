from reportlab.lib.pagesizes import A4
from reportlab.lib import colors
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import mm
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle,
    PageBreak, HRFlowable, KeepTogether
)
from reportlab.lib.enums import TA_CENTER, TA_LEFT, TA_JUSTIFY
from reportlab.pdfgen import canvas
from reportlab.lib.colors import HexColor
import datetime

BRAND       = HexColor('#5B52E0')
BRAND_LIGHT = HexColor('#EEF0FF')
BRAND_DARK  = HexColor('#3D35A8')
TEAL        = HexColor('#3CBFB8')
TEAL_LIGHT  = HexColor('#E6F8F7')
DARK        = HexColor('#1A1D2E')
GREY        = HexColor('#64748B')
GREY_LIGHT  = HexColor('#F1F5F9')
WHITE       = colors.white
SUCCESS     = HexColor('#10B981')
WARNING     = HexColor('#F59E0B')

W, H = A4

def on_page(canv, doc):
    canv.saveState()
    canv.setFillColor(BRAND)
    canv.rect(0, H - 8*mm, W, 8*mm, fill=1, stroke=0)
    canv.setFillColor(WHITE)
    canv.setFont('Helvetica-Bold', 8)
    canv.drawString(15*mm, H - 5.5*mm, 'UNIDAR — Technical Architecture Report')
    canv.drawRightString(W - 15*mm, H - 5.5*mm, datetime.date.today().strftime('%B %d, %Y'))
    canv.setFillColor(GREY_LIGHT)
    canv.rect(0, 0, W, 10*mm, fill=1, stroke=0)
    canv.setFillColor(GREY)
    canv.setFont('Helvetica', 8)
    canv.drawString(15*mm, 3.5*mm, 'Confidential — Internal Documentation')
    canv.drawRightString(W - 15*mm, 3.5*mm, f'Page {doc.page}')
    canv.restoreState()

def on_first_page(canv, doc):
    canv.saveState()
    canv.setFillColor(BRAND)
    canv.rect(0, H - 90*mm, W, 90*mm, fill=1, stroke=0)
    canv.setFillColor(TEAL)
    canv.rect(0, H - 90*mm, W, 3*mm, fill=1, stroke=0)
    canv.setFillColor(GREY_LIGHT)
    canv.rect(0, 0, W, 10*mm, fill=1, stroke=0)
    canv.setFillColor(GREY)
    canv.setFont('Helvetica', 8)
    canv.drawString(15*mm, 3.5*mm, 'Confidential — Internal Documentation')
    canv.drawRightString(W - 15*mm, 3.5*mm, 'Page 1')
    canv.restoreState()

def S(name, **kw): return ParagraphStyle(name, **kw)

cover_title = S('CT', fontSize=32, textColor=WHITE, fontName='Helvetica-Bold', alignment=TA_CENTER, leading=38)
cover_sub   = S('CS', fontSize=14, textColor=HexColor('#C7C3FF'), fontName='Helvetica', alignment=TA_CENTER, leading=20)
cover_meta  = S('CM', fontSize=10, textColor=HexColor('#A8B2C4'), fontName='Helvetica', alignment=TA_CENTER)
h1 = S('H1', fontSize=20, textColor=BRAND, fontName='Helvetica-Bold', spaceBefore=18, spaceAfter=6)
h2 = S('H2', fontSize=13, textColor=DARK, fontName='Helvetica-Bold', spaceBefore=12, spaceAfter=4)
h3 = S('H3', fontSize=10, textColor=BRAND_DARK, fontName='Helvetica-Bold', spaceBefore=8, spaceAfter=2)
body = S('Body', fontSize=9, textColor=DARK, fontName='Helvetica', leading=14, spaceAfter=4, alignment=TA_JUSTIFY)
GREY_S = S('GS', fontSize=8, textColor=GREY, fontName='Helvetica', leading=12, spaceAfter=2)

def hr(): return HRFlowable(width='100%', thickness=0.5, color=HexColor('#E2E8F0'), spaceAfter=6, spaceBefore=4)

def section_header(text, color=BRAND):
    return [
        Spacer(1, 4*mm),
        Table([[Paragraph(text, S('SH', fontSize=16, textColor=WHITE, fontName='Helvetica-Bold', leading=20))]],
            colWidths=[W - 40*mm],
            style=TableStyle([
                ('BACKGROUND', (0,0),(-1,-1), color),
                ('TOPPADDING',(0,0),(-1,-1),8), ('BOTTOMPADDING',(0,0),(-1,-1),8),
                ('LEFTPADDING',(0,0),(-1,-1),12), ('RIGHTPADDING',(0,0),(-1,-1),12),
            ])),
        Spacer(1, 4*mm),
    ]

def info_box(text, bg=BRAND_LIGHT, border=BRAND):
    return Table([[Paragraph(text, S('IB', fontSize=9, textColor=DARK, fontName='Helvetica', leading=13))]],
        colWidths=[W - 40*mm],
        style=TableStyle([
            ('BACKGROUND',(0,0),(-1,-1),bg),
            ('LEFTPADDING',(0,0),(-1,-1),10), ('RIGHTPADDING',(0,0),(-1,-1),10),
            ('TOPPADDING',(0,0),(-1,-1),8), ('BOTTOMPADDING',(0,0),(-1,-1),8),
            ('LINEBEFORE',(0,0),(0,-1),3,border),
        ]))

def std_table(headers, rows, col_widths, header_bg=BRAND):
    data = [[Paragraph(h, S('TH', fontSize=8.5, textColor=WHITE, fontName='Helvetica-Bold')) for h in headers]]
    for row in rows:
        data.append([Paragraph(str(c), S('TD', fontSize=8, textColor=DARK, fontName='Helvetica', leading=11)) for c in row])
    return Table(data, colWidths=col_widths, hAlign='LEFT', repeatRows=1,
        style=TableStyle([
            ('BACKGROUND',(0,0),(-1,0),header_bg),
            ('TOPPADDING',(0,0),(-1,-1),5), ('BOTTOMPADDING',(0,0),(-1,-1),5),
            ('LEFTPADDING',(0,0),(-1,-1),6), ('RIGHTPADDING',(0,0),(-1,-1),6),
            ('ROWBACKGROUNDS',(0,1),(-1,-1),[WHITE, GREY_LIGHT]),
            ('GRID',(0,0),(-1,-1),0.3,HexColor('#CBD5E1')),
            ('VALIGN',(0,0),(-1,-1),'TOP'),
        ]))

OUTPUT = r'C:\Users\ISLEM\Desktop\UNIDAR_Architecture_Report_v2.pdf'
doc = SimpleDocTemplate(OUTPUT, pagesize=A4,
    leftMargin=20*mm, rightMargin=20*mm, topMargin=22*mm, bottomMargin=18*mm,
    title='UNIDAR Architecture Report', author='UNIDAR Engineering')

story = []

# ── COVER ─────────────────────────────────────────────────────────
story.append(Spacer(1, 40*mm))
story.append(Paragraph('UNIDAR', cover_title))
story.append(Spacer(1, 3*mm))
story.append(Paragraph('Student Housing Platform', cover_sub))
story.append(Spacer(1, 6*mm))
story.append(Paragraph('Technical Architecture Report', S('CS2', fontSize=11, textColor=HexColor('#A8B2C4'), fontName='Helvetica-Oblique', alignment=TA_CENTER)))
story.append(Spacer(1, 8*mm))
story.append(Table([['']],colWidths=[W-80*mm], hAlign='CENTER',
    style=TableStyle([('LINEABOVE',(0,0),(0,0),1.5,TEAL),('TOPPADDING',(0,0),(-1,-1),0),('BOTTOMPADDING',(0,0),(-1,-1),0)])))
story.append(Spacer(1, 8*mm))

stats = [['20+','AI Features'],['10','ML Models'],['13','Symfony\nBundles'],['100%','Local / PHP\n(Zero Cost)']]
stat_cells = []
for val, lbl in stats:
    stat_cells.append(Table([
        [Paragraph(val, S('SV', fontSize=22, textColor=BRAND, fontName='Helvetica-Bold', alignment=TA_CENTER))],
        [Paragraph(lbl, S('SL', fontSize=8, textColor=GREY, fontName='Helvetica', alignment=TA_CENTER, leading=10))],
    ], colWidths=[32*mm], style=TableStyle([
        ('BACKGROUND',(0,0),(-1,-1),WHITE),
        ('TOPPADDING',(0,0),(-1,-1),8), ('BOTTOMPADDING',(0,0),(-1,-1),8),
        ('BOX',(0,0),(-1,-1),0.5,HexColor('#E2E8F0')),
    ])))

story.append(Table([stat_cells], colWidths=[36*mm]*4, hAlign='CENTER',
    style=TableStyle([('LEFTPADDING',(0,0),(-1,-1),2),('RIGHTPADDING',(0,0),(-1,-1),2),
        ('TOPPADDING',(0,0),(-1,-1),0),('BOTTOMPADDING',(0,0),(-1,-1),0)])))
story.append(Spacer(1, 60*mm))
story.append(Paragraph(
    f'Generated: {datetime.date.today().strftime("%B %d, %Y")}  •  Symfony 7.4  •  PHP 8.1  •  MariaDB 10.4  •  Rubix ML 2.5',
    cover_meta))
story.append(PageBreak())

# ── SECTION 1 — AI ARCHITECTURE ─────────────────────────────────
story += section_header('SECTION 1 — AI Architecture Overview', BRAND)
story.append(Paragraph('Zero-Cost Local Intelligence', h1))
story.append(Paragraph(
    'UNIDAR\'s AI stack is designed for maximum efficiency and zero recurring costs. Major features previously '
    'reliant on LLMs have been migrated to <b>pure PHP, rule-based, and algorithmic engines</b>. '
    'Heavy computational tasks use <b>Rubix ML</b>, while semantic tasks use a hybrid approach with '
    'local Ollama support and PHP fallbacks.', body))
story.append(Spacer(1, 3*mm))
story.append(std_table(
    ['Layer', 'Technology', 'Implementation', 'Status'],
    [
        ['Semantic', 'EmbeddingService', 'TF-IDF (512-dim) / nomic-embed-text', 'Hybrid (Local Fallback)'],
        ['Rule Engines', 'PHP regex / Logic', 'Knowledge-based mediation & analysis', 'Production (Stable)'],
        ['Template Engine', 'Pure PHP Phrase Banks', 'Description & Summary generation', 'Production (Stable)'],
        ['ML Models', 'Rubix ML (PHP)', 'RandomForest, GradientBoost, KNN', 'Production (Stable)'],
        ['Algorithmic', 'Pure PHP', 'Scoring & Classification logic', 'Production (Stable)'],
    ], [40*mm, 45*mm, 62*mm, 42*mm]))

story.append(Spacer(1, 5*mm))
story.append(Paragraph('Core AI Service Files', h2))
story.append(std_table(
    ['Service File', 'Current Implementation'],
    [
        ['src/Service/AI/LlmService.php', 'Ollama Gateway (llama3). Now primary for Admin general tasks.'],
        ['src/Service/AI/EmbeddingService.php', 'embed(), similarity(). Hybrid: TF-IDF (Pure PHP). Upgrades to Ollama if available.'],
        ['src/Service/AI/ListingDescriptionGeneratorService.php', '<b>No LLM</b>. Uses phrase banks and rich template logic for EN/FR/AR.'],
        ['src/Service/AI/LeaseRiskAnalyzerService.php', '<b>No LLM</b>. 25+ multilingual rule patterns for automatic risk scoring.'],
        ['src/Service/AI/DisputeMediatorService.php', '<b>No LLM</b>. Knowledge-based engine for structured neutral mediation.'],
        ['src/Service/AI/ReviewSentimentService.php', '<b>No LLM</b>. Algorithmic word-list sentiment + context-aware theme extraction.'],
    ], [75*mm, 92*mm]))

story.append(Spacer(1, 4*mm))
story.append(info_box(
    '<b>Privacy & Cost:</b> All AI logic runs entirely on the host server. No data is sent to external APIs (OpenAI, Claude, etc.), '
    'ensuring 100% privacy and zero operational costs.',
    TEAL_LIGHT, TEAL))
story.append(PageBreak())

# ── SECTION 2 — ALGORITHMIC FEATURES ────────────────────────────
story += section_header('SECTION 2 — AI Features: Algorithmic & Rule-Based', TEAL)
algo_ai_features = [
    {'num':'#4','name':'Listing Description Generator','service':'ListingDescriptionGeneratorService',
     'route':'POST /api/ai/listing/{id}/description','access':'ROLE_USER','tech':'Pure PHP Template Engine',
     'desc':'Generates professional EN/FR/AR real-estate descriptions from listing data using a rich phrase-bank system. Mimics LLM quality without external dependencies. Deterministic based on listing ID for consistency.'},
    {'num':'#10','name':'Lease Risk Analyzer','service':'LeaseRiskAnalyzerService',
     'route':'POST /api/ai/lease/analyze','access':'ROLE_USER','tech':'25+ Rule Patterns (Regex)',
     'desc':'Analyzes lease text for 25+ multilingual risk patterns. Flags automatic renewals, maintenance disclaimers, privacy violations, and illegal entry clauses. Computes a safety score (0–100) and provides specific legal advice.'},
    {'num':'#11','name':'AI Dispute Mediator','service':'DisputeMediatorService',
     'route':'POST /api/ai/dispute/mediate','access':'ROLE_ADMIN only','tech':'Rule-based Mediation Engine',
     'desc':'Extracts key claims from tenant and owner statements using signal patterns. Applies a per-type resolution knowledge base (deposit, noise, etc.) to produce a structured, neutral resolution path.'},
    {'num':'#18','name':'Review Sentiment & Theme Extractor','service':'ReviewSentimentService',
     'route':'POST /api/ai/reviews/sentiment','access':'Public','tech':'Word-list Sentiment + Context Extraction',
     'desc':'Uses weighted word-lists to score sentiment in EN/FR/AR. Extracts 8 specific themes (cleanliness, wifi, etc.) and generates an algorithmic summary comparing praises vs. criticisms.'},
]
for f in algo_ai_features:
    story.append(KeepTogether([
        Paragraph(f'{f["num"]} — {f["name"]}', h2),
        std_table(['Service','Route','Access','Implementation'],
            [[f['service'],f['route'],f['access'],f['tech']]],
            [50*mm,52*mm,22*mm,38*mm]),
        Spacer(1,2*mm), Paragraph(f['desc'], body), Spacer(1,4*mm), hr(),
    ]))

story.append(KeepTogether([
    Paragraph('#1 — Natural Language Search', h2),
    std_table(['Service','API Route','Access','Implementation'],
        [['NaturalLanguageSearchService','GET/POST /api/ai/search','Public','Semantic + Rule-based Hybrid']],
        [50*mm,52*mm,22*mm,38*mm]),
    Spacer(1,2*mm),
    Paragraph('Parses free-text queries into structured intent using a rule-based parser. Ranks listings by combining a semantic score (EmbeddingService) with rule boosts for city, price, and amenities. Supports EN/FR/AR.', body),
]))
story.append(PageBreak())

# ── SECTION 3 — ML MODELS ────────────────────────────────────────
story += section_header('SECTION 3 — AI Features: Rubix ML Models', BRAND_DARK)
story.append(Paragraph(
    'All ML models are trained via CLI commands and stored as serialised PHP files in '
    '<font name="Courier">var/ml_models/</font>. Each has a companion '
    '<font name="Courier">.meta.json</font> file tracking training date, sample count, and accuracy metrics.', body))
story.append(Spacer(1, 3*mm))

ml_features = [
    {'num':'#6','name':'Fraud / Fake Listing Detector','service':'FraudDetectionService','model':'FraudDetectionModel',
     'algo':'RandomForest (100 trees, depth 5) + ZScale','cmd':'ai:train:fraud',
     'route':'GET /api/ai/listing/{id}/fraud-score','access':'ROLE_ADMIN or own listing',
     'desc':'Rule-based signals (40% weight) + RandomForest classifier (60% weight). Detects price anomalies, account age issues, and description patterns.'},
    {'num':'#7','name':'Energy Cost Estimator','service':'EnergyCostEstimatorService','model':'EnergyCostModel',
     'algo':'GradientBoost (RegressionTree depth 4)','cmd':'ai:train:energy-cost',
     'route':'GET /api/ai/listing/{id}/energy','access':'Public',
     'desc':'Formula-based default + ML regression if trained. Predicts kWh/month and TND/month using 350 synthetic samples.'},
    {'num':'#9','name':'Roommate AI Matching','service':'RoommateAiMatchingService','model':'RoommateCompatibilityModel',
     'algo':'KNearestNeighbors (k=5) + ZScale','cmd':'app:ai:train-roommate',
     'route':'GET /api/roommate-matches','access':'ROLE_USER',
     'desc':'Feature extractor mapping cleanliness, sleep, noise, budget into 11-dim vector. Euclidean distance for weighted compatibility scoring.'},
    {'num':'#12','name':'Dynamic Market Pricing','service':'DynamicPricingService','model':'DynamicPricingModel',
     'algo':'GradientBoost (RegressionTree depth 5)','cmd':'ai:train:dynamic-pricing',
     'route':'GET /api/ai/listing/{id}/dynamic-pricing','access':'ROLE_USER',
     'desc':'Models vacancy, seasonality, and local competition. Provides Raise/Hold/Lower suggestions with ±40% guardrails.'},
    {'num':'#15','name':'Behavioural Anomaly Detection','service':'BehaviouralAnomalyService','model':'BehaviouralAnomalyModel',
     'algo':'RandomForest (120 trees, depth 5)','cmd':'ai:train:behavioural-anomaly',
     'route':'GET /api/ai/user/{id}/anomaly','access':'ROLE_ADMIN only',
     'desc':'Detects scrapers, spammers, and malicious activity by analyzing message rate, views/session, and unique IPs.'},
    {'num':'#16','name':'Listing Performance Predictor','service':'ListingPerformanceService','model':'ListingPerformanceModel',
     'algo':'GradientBoost (RegressionTree depth 4)','cmd':'ai:train:listing-performance',
     'route':'GET /api/ai/listing/{id}/performance','access':'ROLE_USER',
     'desc':'Predicts "days to fill" (1–120) based on price ratio, photos, and location. Provides performance grades A–F.'},
    {'num':'#17','name':'Churn Predictor','service':'ChurnPredictionService','model':'ChurnPredictionModel',
     'algo':'RandomForest (150 trees, depth 5)','cmd':'ai:train:churn',
     'route':'GET /api/ai/user/{id}/churn','access':'ROLE_ADMIN',
     'desc':'Analyzes login frequency, sessions, and profile completion to predict user churn probability and suggest re-engagement.'},
]
for f in ml_features:
    story.append(KeepTogether([
        Paragraph(f'{f["num"]} — {f["name"]}', h2),
        std_table(['Service','Model Class','Train Command'],[[f['service'],f['model'],f['cmd']]],[55*mm,52*mm,55*mm]),
        Spacer(1,1*mm),
        std_table(['API Route','Access','Algorithm'],[[f['route'],f['access'],f['algo']]],[80*mm,27*mm,55*mm]),
        Spacer(1,2*mm), Paragraph(f['desc'], body), Spacer(1,5*mm), hr(),
    ]))
story.append(PageBreak())

# ── SECTION 4 — PURE PHP FEATURES ───────────────────────────────
story += section_header('SECTION 4 — AI Features: Pure PHP / Utility', HexColor('#059669'))
algo_features = [
    {'num':'#5','name':'Photo Quality Scorer','service':'PhotoQualityService',
     'route':'GET /api/ai/listing/{id}/photo-quality','access':'ROLE_USER',
     'desc':'PHP GD extension: Brightness grid sampling, pixel variance (blur), and room coverage keywords. Entirely algorithmic.'},
    {'num':'#8','name':'Roommate Chemistry Score','service':'RoommateChemistryService',
     'route':'POST /api/ai/roommate/chemistry','access':'ROLE_USER',
     'desc':'6-dimension weighted scoring (Sleep, Cleanliness, etc.) with seasonal stress timeline and group formation algorithm.'},
    {'num':'#13','name':'Total Cost of Living','service':'CostOfLivingService',
     'route':'GET/POST /api/ai/listing/{id}/cost-of-living','access':'Public',
     'desc':'Haversine distance to universities + 2024 Tunisia cost constants (internet, transport, groceries).'},
    {'num':'#14','name':'Document Verification','service':'DocumentVerificationService',
     'route':'POST /api/ai/document/verify','access':'ROLE_USER',
     'desc':'Tesseract OCR via shell_exec + Regex extraction for Tunisian IDs and enrollment certificates.'},
    {'num':'#19','name':'Campus Commute Score','service':'CommuteScoreService',
     'route':'GET /api/ai/listing/{id}/commute','access':'Public',
     'desc':'Haversine formula to 11 Tunisian universities with 4 transport mode factors and road multipliers.'},
    {'num':'#20','name':'Neighbourhood Vibe Classifier','service':'NeighbourhoodVibeService',
     'route':'GET /api/ai/listing/{id}/neighbourhood','access':'Public',
     'desc':'Inverse distance weighting from 100+ POI clusters across 11 Tunisian cities. classifies atmosphere (nightlife, quiet, etc.).'},
]
for f in algo_features:
    story.append(KeepTogether([
        Paragraph(f'{f["num"]} — {f["name"]}', h2),
        std_table(['Service','API Route','Access'],[[f['service'],f['route'],f['access']]],[60*mm,80*mm,22*mm]),
        Spacer(1,2*mm), Paragraph(f['desc'], body), Spacer(1,4*mm), hr(),
    ]))

story.append(KeepTogether([
    Paragraph('Voice Commander', h2),
    std_table(['Service','Controller','Routes'],
        [['VoiceIntentClassifier','VoiceController','POST /voice/intent  |  POST /voice/transcribe  |  POST /voice/speak']],
        [50*mm,35*mm,77*mm]),
    Spacer(1,2*mm),
    Paragraph('Pure PHP regex intent classifier (40+ intents). Auto-detects EN/FR/AR. Uses Browser Web Speech API as default or proxies to local Kokoro/Whisper microservices if available.', body),
]))
story.append(PageBreak())

# ── SECTION 5 — TRAINING PIPELINE ───────────────────────────────
story += section_header('SECTION 5 — ML Training Pipeline', HexColor('#7C3AED'))
story.append(info_box(
    '<b>All training is triggered manually via CLI.</b> Orchestrator command <b>app:ai:retrain-all</b> '
    'chains model training. Training status and accuracy are tracked in <b>.meta.json</b> files.', BRAND_LIGHT, BRAND))
story.append(Spacer(1,4*mm))
story.append(std_table(
    ['Console Command','Model','Algorithm','Data Source'],
    [
        ['app:ai:retrain-all','(orchestrator)','—','Chains all models'],
        ['app:ai:train-listing-quality','listing_quality','RandomForest','DB + Synthetic'],
        ['app:ai:train-price','price_recommendation','KDNeighborsRegressor','DB + Synthetic'],
        ['app:ai:train-roommate','roommate_compat.','KNearestNeighbors','DB + Synthetic'],
        ['ai:train:fraud','fraud_detection','RandomForest','Synthetic + Real'],
        ['ai:train:churn','churn_prediction','RandomForest','500 Synthetic samples'],
        ['ai:train:dynamic-pricing','dynamic_pricing','GradientBoost','Formulaic + Synthetic'],
        ['ai:train:energy-cost','energy_cost','GradientBoost','350 samples'],
        ['ai:train:listing-performance','listing_performance','GradientBoost','Real + Rule-based'],
        ['ai:train:behavioural-anomaly','behavioural_anomaly','RandomForest','500 Synthetic + Real'],
    ], [50*mm, 40*mm, 45*mm, 45*mm]))
story.append(Spacer(1,5*mm))
story.append(Paragraph('Async Messenger Workers', h2))
story.append(std_table(
    ['Message Class','Purpose','Transport'],
    [
        ['TrainModelMessage','Async ML model training','async (Doctrine)'],
        ['BulkScoreListingsMessage','Score all active listings with OptimizationService','async (Doctrine)'],
        ['SendRoommateMatchNotificationMessage','Send email alerts for compatible pairs','async (Doctrine)'],
    ], [70*mm, 62*mm, 30*mm]))
story.append(PageBreak())

# ── SECTION 6 — BUNDLES ──────────────────────────────────────────
story += section_header('SECTION 6 — Symfony Bundles & Workflow', BRAND)
bundles = [
    {'name':'FrameworkBundle','pkg':'symfony/framework-bundle v7.4','env':'all','cfg':'config/packages/framework.yaml','color':BRAND,
     'desc':'<b>The core of Symfony.</b> Handles routing, DI container auto-wiring, HTTP kernel, sessions, and CSRF protection.'},
    {'name':'DoctrineBundle','pkg':'doctrine/doctrine-bundle ^2.18','env':'all','cfg':'config/packages/doctrine.yaml','color':HexColor('#F97316'),
     'desc':'<b>ORM integration.</b> Connects to MariaDB 10.4. Features SoftDeleteable filters and lazy proxy objects for performance.'},
    {'name':'SecurityBundle','pkg':'symfony/security-bundle v7.4','env':'all','cfg':'config/packages/security.yaml','color':HexColor('#EF4444'),
     'desc':'<b>Auth & Authorization.</b> Implements Argon2id hashing, form/JWT login, and complex role hierarchies (ADMIN/OWNER/STUDENT).'},
    {'name':'TwigBundle','pkg':'symfony/twig-bundle v7.4','env':'all','cfg':'config/packages/twig.yaml','color':HexColor('#22C55E'),
     'desc':'<b>Templating.</b> Compiled PHP templates with master layouts and inheritance. Strict variable checking enabled in test mode.'},
    {'name':'LexikJWTAuthenticationBundle','pkg':'lexik/jwt-authentication-bundle ^3.1','env':'all','cfg':'config/packages/lexik_jwt_authentication.yaml','color':HexColor('#EC4899'),
     'desc':'<b>Stateless API Auth.</b> Uses RSA-signed tokens for desktop app integration with a 1-hour TTL.'},
    {'name':'VichUploaderBundle','pkg':'vich/uploader-bundle ^2.9','env':'all','cfg':'config/packages/vich_uploader.yaml','color':HexColor('#F59E0B'),
     'desc':'<b>File Management.</b> Automates uploads for listings, IDs, and signatures with unique ID naming and Doctrine mapping.'},
    {'name':'StofDoctrineExtensionsBundle','pkg':'stof/doctrine-extensions-bundle ^1.13','env':'all','cfg':'stof_doctrine_extensions.yaml','color':HexColor('#F97316'),
     'desc':'<b>Entity Behaviors.</b> Automates created_at/updated_at timestamps and soft-deletion (retains records with deleted_at).'},
    {'name':'SentryBundle','pkg':'sentry/sentry-symfony ^5.0','env':'all','cfg':'env: SENTRY_DSN','color':HexColor('#EF4444'),
     'desc':'<b>Error Monitoring.</b> Automatically catches exceptions and PHP fatals, sending stack traces to Sentry.io in production.'},
    {'name':'EndroidQrCodeBundle','pkg':'endroid/qr-code-bundle ^6.0','env':'all','cfg':'endroid_qr_code.yaml','color':HexColor('#1D4ED8'),
     'desc':'<b>QR Verification.</b> Generates verification QR codes for digital contracts, linking to an authenticity check page.'},
]

for b in bundles:
    env_color = SUCCESS if b['env'] == 'all' else WARNING
    story.append(KeepTogether([
        Table([[
            Paragraph(b['name'], S('BN', fontSize=12, textColor=WHITE, fontName='Helvetica-Bold')),
            Paragraph(b['pkg'], S('BP', fontSize=8, textColor=WHITE, fontName='Courier', alignment=TA_LEFT)),
        ]], colWidths=[W-40*mm-85*mm, 85*mm],
            style=TableStyle([
                ('BACKGROUND',(0,0),(-1,-1),b['color']),
                ('TOPPADDING',(0,0),(-1,-1),7), ('BOTTOMPADDING',(0,0),(-1,-1),7),
                ('LEFTPADDING',(0,0),(-1,-1),10), ('RIGHTPADDING',(0,0),(-1,-1),10),
                ('VALIGN',(0,0),(-1,-1),'MIDDLE'),
            ])),
        Table([[
            Paragraph(f'<b>Config:</b> {b["cfg"]}', S('BC', fontSize=8, textColor=GREY, fontName='Helvetica')),
            Paragraph(f'<b>ENV:</b> {b["env"]}', S('BE', fontSize=8, textColor=env_color, fontName='Helvetica-Bold')),
        ]], colWidths=[W-40*mm-30*mm, 30*mm],
            style=TableStyle([
                ('BACKGROUND',(0,0),(-1,-1),GREY_LIGHT),
                ('TOPPADDING',(0,0),(-1,-1),4), ('BOTTOMPADDING',(0,0),(-1,-1),4),
                ('LEFTPADDING',(0,0),(-1,-1),10), ('RIGHTPADDING',(0,0),(-1,-1),6),
            ])),
        Spacer(1,2*mm),
        Paragraph(b['desc'], body),
        Spacer(1,5*mm), hr(),
    ]))

story.append(PageBreak())

# ── SECTION 7 — LIBRARIES & DASHBOARD ─────────────────────────────
story += section_header('SECTION 7 — Libraries & AI Dashboard', HexColor('#0F766E'))
story.append(std_table(
    ['Library','Version','Purpose','Implementation'],
    [
        ['rubix/ml','^2.5','Native PHP Machine Learning','RF, GB, KNN, Regressors'],
        ['dompdf/dompdf','^3.1','PHP-native PDF Rendering','Contract generation'],
        ['tesseract-ocr','shell','Optical Character Recognition','Document verification'],
        ['messenger','s7.4','Async queue management','Doctrine-backed queue'],
        ['http-client','s7.4','Internal & external requests','EmbeddingService & LLM'],
    ], [40*mm, 20*mm, 65*mm, 55*mm]))

story.append(Spacer(1,6*mm))
story.append(Paragraph('Admin AI Dashboard Summary', h1))
story.append(std_table(
    ['Route','Content','Alert Logic'],
    [
        ['/admin/ai','General status, LLM availability, training metadata','ML Trained? AND samples > threshold'],
        ['/admin/ai/fraud-queue','Pending listings ranked by fraud score','Anomaly detection rules AND RF classifier'],
        ['/admin/ai/churn-report','Inactive users with high churn probability','Behavioural features AND RF probability'],
        ['/admin/ai/anomaly-report','Suspicious users by risk score','Rate limiting AND Anomaly RF model'],
    ], [45*mm, 70*mm, 67*mm]))

story.append(Spacer(1,6*mm))
story.append(info_box(
    '<b>Summary:</b> UNIDAR leverages a hybrid AI architecture that prioritizes speed and cost-effectiveness via algorithmic models '
    'while maintaining semantic capabilities through local embedding fallbacks. This ensures a premium student experience with zero '
    'infrastructure overhead.',
    TEAL_LIGHT, TEAL))
story.append(Spacer(1,8*mm))
story.append(hr())
story.append(Paragraph(
    f'UNIDAR Technical Architecture Report  —  Generated {datetime.date.today().strftime("%B %d, %Y")}  '
    '—  Symfony 7.4 / PHP 8.1 / Rubix ML 2.5 / Hybrid AI',
    S('Footer', fontSize=8, textColor=GREY, alignment=TA_CENTER)))

doc.build(story, onFirstPage=on_first_page, onLaterPages=on_page)
print(f'PDF saved to: {OUTPUT}')
