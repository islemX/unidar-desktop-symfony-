package tn.unidar.desktop.controllers;

import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.fxml.Initializable;
import javafx.scene.Parent;
import javafx.scene.control.Button;
import javafx.scene.control.ComboBox;
import javafx.scene.control.Label;
import javafx.scene.layout.HBox;
import javafx.scene.layout.StackPane;
import javafx.scene.layout.VBox;
import tn.unidar.desktop.models.User;
import tn.unidar.desktop.services.AuthService;
import tn.unidar.desktop.services.LanguageManager;
import tn.unidar.desktop.services.NavigationService;
import tn.unidar.desktop.services.voice.VoiceController;
import tn.unidar.desktop.utils.I18n;

import java.net.URL;
import java.util.ResourceBundle;

public class ShellController implements Initializable {

    @FXML private StackPane mainContent;
    @FXML private StackPane contentWrapper;

    // Sidebar nav buttons
    @FXML private Button btnHome;
    @FXML private Button btnList;
    @FXML private Button btnRoom;
    @FXML private Button btnDash;
    @FXML private Button btnVerif;
    @FXML private Button btnSub;
    @FXML private Button btnOwner;

    // Auth-aware sections
    @FXML private VBox mainNav;     // Listings + Roommates (logged-in only)
    @FXML private VBox authNav;     // Dashboard/Verif/Premium
    @FXML private VBox userSection;
    @FXML private VBox guestSection;

    // User info labels
    @FXML private Label lblAvatar;
    @FXML private Label lblUserName;
    @FXML private Label lblUserRole;

    // Language dropdown
    @FXML private ComboBox<String> langCombo;
    @FXML private Label lblNavMain, lblNavAccount, lblNavLanguage;
    @FXML private Button btnLogout, btnLoginNav, btnRegisterNav;
    @FXML private Label lblDrawerTitle, lblVoiceHint;

    // Floating messages drawer
    @FXML private HBox    msgDrawer;
    @FXML private StackPane msgContent;
    @FXML private Button  btnMsgFab;

    // Voice bar
    @FXML private Button btnMic;
    @FXML private Label  lblVoiceStatus;

    private VoiceController voiceController;
    private boolean isListening   = false;
    private boolean msgDrawerOpen = false;
    private Button activeBtn = null;

    @Override
    public void initialize(URL location, ResourceBundle resources) {
        NavigationService nav = NavigationService.getInstance();
        nav.setContentArea(mainContent);
        nav.setNavigationListener(this::onNavigate);
        nav.setAuthChangeListener(this::refreshAuthState);

        // Language options
        langCombo.getItems().addAll("English 🇬🇧", "Français 🇫🇷", "العربية 🇹🇳");
        
        // Match combo to current manager state
        String currentL = NavigationService.getInstance().getLanguage();
        if ("fr".equals(currentL)) langCombo.setValue("Français 🇫🇷");
        else if ("ar".equals(currentL)) langCombo.setValue("العربية 🇹🇳");
        else langCombo.setValue("English 🇬🇧");

        // Listen for global changes
        LanguageManager.getInstance().localeProperty().addListener((obs, oldV, newV) -> {
            Platform.runLater(() -> translateShell(newV.getLanguage()));
        });

        translateShell(nav.getLanguage());
        voiceController = new VoiceController();
        voiceController.setStatusListener(status -> {
            Platform.runLater(() -> lblVoiceStatus.setText(status));
        });
        voiceController.setCommandListener(cmd -> {
            if ("LOGOUT".equals(cmd)) Platform.runLater(this::handleLogout);
            else if ("CLOSE_DRAWER".equals(cmd)) Platform.runLater(this::closeMsgDrawer);
            else if (cmd.startsWith("LANG_")) {
                Platform.runLater(() -> {
                    String code = cmd.substring(5).toLowerCase();
                    if ("en".equals(code)) langCombo.setValue("English 🇬🇧");
                    else if ("fr".equals(code)) langCombo.setValue("Français 🇫🇷");
                    else if ("ar".equals(code)) langCombo.setValue("العربية 🇹🇳");
                    handleLanguageChange();
                });
            }
        });
        voiceController.setStopListener(() -> {
            Platform.runLater(this::stopVoiceUI);
        });
        refreshAuthState();

        // Start on Home (or AdminDashboard if already logged in as admin)
        User startUser = AuthService.getInstance().getCurrentUser();
        if (startUser != null && "admin".equalsIgnoreCase(startUser.getRole())) {
            nav.navigateTo("AdminDashboard.fxml");
        } else {
            nav.navigateTo("Home.fxml");
        }
    }

    // ─── Auth state refresh ───────────────────────────────
    public void refreshAuthState() {
        boolean loggedIn = AuthService.getInstance().isLoggedIn();
        User user = AuthService.getInstance().getCurrentUser();
        boolean isAdmin = user != null && "admin".equalsIgnoreCase(user.getRole());
        boolean isOwner = user != null && "owner".equalsIgnoreCase(user.getRole());

        // Show/hide nav groups
        setVisible(mainNav,      loggedIn && !isAdmin);   // Listings + Roommates hidden for admin
        setVisible(authNav,      loggedIn);
        setVisible(userSection,  loggedIn);
        setVisible(guestSection, !loggedIn);
        setVisible(btnMsgFab,    loggedIn && !isAdmin);   // FAB hidden for admin

        if (loggedIn && user != null) {
            String name = user.getFullName();
            lblUserName.setText(name);
            lblUserRole.setText(capitalize(user.getRole()));
            lblAvatar.setText(name.isEmpty() ? "U" : String.valueOf(name.charAt(0)).toUpperCase());

            // Sidebar Visibility for Owner vs Student
            if (isOwner) {
                setVisible(btnRoom,  false); // Hide Roommates
                setVisible(btnVerif, false); // Hide Verification
                setVisible(btnSub,   false); // Hide Premium
                setVisible(btnOwner, true);  // Show My Listings
            } else if (isAdmin) {
                // Admin: only show Dashboard button; hide Verification, Premium, Owner
                setVisible(btnVerif, false);
                setVisible(btnSub,   false);
                setVisible(btnOwner, false);
            } else {
                // Student
                setVisible(btnRoom,  true);
                setVisible(btnVerif, true);
                setVisible(btnSub,   true);
                setVisible(btnOwner, false);
            }

            if (isAdmin) {
                btnDash.setText("🛡️  Admin Dashboard");
            } else {
                String lang = NavigationService.getInstance().getLanguage();
                if ("fr".equals(lang))       btnDash.setText("📊  Tableau de bord");
                else if ("ar".equals(lang))  btnDash.setText("📊  لوحة التحكم");
                else                         btnDash.setText("📊  Dashboard");
            }
        }
        translateShell(NavigationService.getInstance().getLanguage());
    }

    // ─── Navigation listener (highlight active button) ───
    private void onNavigate(String page) {
        if (activeBtn != null) activeBtn.getStyleClass().remove("active");
        activeBtn = switch (page) {
            case "Home.fxml"           -> btnHome;
            case "Listings.fxml"       -> btnList;
            case "ListingDetail.fxml"  -> btnList;
            case "Roommates.fxml"      -> btnRoom;
            case "Dashboard.fxml"      -> btnDash;
            case "AdminDashboard.fxml" -> btnDash;
            case "Verification.fxml"   -> btnVerif;
            case "Subscription.fxml"   -> btnSub;
            case "OwnerListings.fxml"  -> btnOwner;
            default -> null;
        };
        if (activeBtn != null && !activeBtn.getStyleClass().contains("active")) {
            activeBtn.getStyleClass().add("active");
        }
        // Close messages drawer on page change
        if (msgDrawerOpen) closeMsgDrawer();
    }

    // ─── Nav handlers ─────────────────────────────────────
    @FXML private void handleHome()          { NavigationService.getInstance().navigateTo("Home.fxml"); }
    @FXML private void handleListings()      { requireAuth(() -> NavigationService.getInstance().navigateTo("Listings.fxml")); }
    @FXML private void handleRoommates()     { requireAuth(() -> NavigationService.getInstance().navigateTo("Roommates.fxml")); }
    @FXML private void handleDashboard() {
        requireAuth(() -> {
            User u = AuthService.getInstance().getCurrentUser();
            boolean isAdmin = u != null && "admin".equalsIgnoreCase(u.getRole());
            NavigationService.getInstance().navigateTo(isAdmin ? "AdminDashboard.fxml" : "Dashboard.fxml");
        });
    }
    @FXML private void handleVerification()  { requireAuth(() -> NavigationService.getInstance().navigateTo("Verification.fxml")); }
    @FXML private void handleSubscription()  { requireAuth(() -> NavigationService.getInstance().navigateTo("Subscription.fxml")); }
    @FXML private void handleOwnerListings() { requireAuth(() -> NavigationService.getInstance().navigateTo("OwnerListings.fxml")); }

    @FXML private void handleLoginNav()    { NavigationService.getInstance().navigateTo("Login.fxml"); }
    @FXML private void handleRegisterNav() { NavigationService.getInstance().navigateTo("Register.fxml"); }

    @FXML
    private void handleLogout() {
        AuthService.getInstance().logout();
        if (msgDrawerOpen) closeMsgDrawer();
        refreshAuthState();
        NavigationService.getInstance().navigateTo("Home.fxml");
    }

    // ─── Floating Messages Drawer ─────────────────────────
    @FXML
    private void toggleMsgDrawer() {
        if (msgDrawerOpen) {
            closeMsgDrawer();
        } else {
            openMsgDrawer();
        }
    }

    private void openMsgDrawer() {
        if (!AuthService.getInstance().isLoggedIn()) {
            NavigationService.getInstance().navigateTo("Login.fxml");
            return;
        }
        // Load Messages.fxml into the drawer panel
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("/fxml/Messages.fxml"));
            Parent msgsView = loader.load();
            msgContent.getChildren().setAll(msgsView);
        } catch (Exception e) {
            System.err.println("[Shell] Message drawer load error: " + e.getMessage());
        }
        setVisible(msgDrawer, true);
        msgDrawerOpen = true;
        btnMsgFab.setText("✕");
        btnMsgFab.setStyle(
            "-fx-background-color: #ef4444; -fx-background-radius: 50; -fx-text-fill: white;" +
            "-fx-font-size: 18px; -fx-min-width: 56; -fx-min-height: 56;" +
            "-fx-max-width: 56; -fx-max-height: 56; -fx-cursor: hand;" +
            "-fx-translate-x: -24; -fx-translate-y: -84;" +
            "-fx-effect: dropshadow(three-pass-box, rgba(239,68,68,0.45), 16, 0, 0, 4);"
        );
    }

    @FXML
    private void closeMsgDrawer() {
        setVisible(msgDrawer, false);
        msgContent.getChildren().clear();
        msgDrawerOpen = false;
        btnMsgFab.setText("💬");
        btnMsgFab.setStyle(
            "-fx-background-color: #4f46e5; -fx-background-radius: 50; -fx-text-fill: white;" +
            "-fx-font-size: 20px; -fx-min-width: 56; -fx-min-height: 56;" +
            "-fx-max-width: 56; -fx-max-height: 56; -fx-cursor: hand;" +
            "-fx-translate-x: -24; -fx-translate-y: -84;" +
            "-fx-effect: dropshadow(three-pass-box, rgba(79,70,229,0.45), 16, 0, 0, 4);"
        );
    }

    // ─── Language ─────────────────────────────────────────
    @FXML
    private void handleLanguageChange() {
        String selected = langCombo.getValue();
        if (selected == null) return;
        String langCode = selected.contains("Français") ? "fr" : selected.contains("العربية") ? "ar" : "en";

        NavigationService nav = NavigationService.getInstance();
        nav.setLanguage(langCode);
        LanguageManager.getInstance().setLocale(new java.util.Locale(langCode));

        // Reload current page to refresh content area
        String currentPage = nav.getCurrentPage();
        if (currentPage != null) {
            nav.navigateTo(currentPage);
        }
    }

    private void translateShell(String lang) {
        btnHome.setText(I18n.t("shell.nav.home", lang));
        btnList.setText(I18n.t("shell.nav.listings", lang));
        btnRoom.setText(I18n.t("shell.nav.roommates", lang));

        User curUser  = AuthService.getInstance().getCurrentUser();
        boolean isAdmin = curUser != null && "admin".equalsIgnoreCase(curUser.getRole());
        if (isAdmin) {
            btnDash.setText(I18n.t("shell.nav.admin", lang));
        } else {
            btnDash.setText(I18n.t("shell.nav.dash", lang));
        }

        btnVerif.setText(I18n.t("shell.nav.verif", lang));
        btnSub.setText(I18n.t("shell.nav.premium", lang));
        btnOwner.setText(I18n.t("shell.nav.owner", lang));

        lblNavMain.setText(I18n.t("shell.nav.main", lang));
        lblNavAccount.setText(I18n.t("shell.nav.account", lang));
        lblNavLanguage.setText(I18n.t("shell.nav.language", lang));

        btnLogout.setText(I18n.t("shell.auth.signout", lang));
        btnLoginNav.setText(I18n.t("shell.auth.signin", lang));
        btnRegisterNav.setText(I18n.t("shell.auth.register", lang));

        lblDrawerTitle.setText(I18n.t("shell.msg.title", lang));
        lblVoiceHint.setText(I18n.t("shell.voice.hint", lang));
        if (!isListening) {
            lblVoiceStatus.setText(I18n.t("shell.voice.ready", lang));
        } else {
            lblVoiceStatus.setText(I18n.t("shell.voice.listening", lang));
        }
    }

    // ─── Voice ────────────────────────────────────────────
    @FXML
    private void toggleVoice() {
        if (!isListening) {
            isListening = true;
            lblVoiceStatus.setText("Listening…  Go ahead, say something!");
            btnMic.getStyleClass().add("recording");
            
            // Welcome message - Start listening ONLY after welcome finishes
            String lang = NavigationService.getInstance().getLanguage();
            String welcome = I18n.t("shell.voice.welcome", lang);
            voiceController.speak(welcome, () -> {
                Platform.runLater(() -> voiceController.startListening());
            });
        } else {
            voiceController.stopListening();
            // Note: stopVoiceUI() will be called via the listener from voiceController.stopListening()
        }
    }

    private void stopVoiceUI() {
        if (!isListening) return;
        isListening = false;
        btnMic.getStyleClass().remove("recording");
        // We don't overwrite the "Processing..." status here so the user sees it's working
        if (lblVoiceStatus.getText().contains("Listening")) {
            lblVoiceStatus.setText("Voice Commander Ready");
        }
    }

    // ─── Helpers ──────────────────────────────────────────
    private void requireAuth(Runnable action) {
        if (AuthService.getInstance().isLoggedIn()) {
            action.run();
        } else {
            NavigationService.getInstance().navigateTo("Login.fxml");
        }
    }

    private void setVisible(javafx.scene.Node node, boolean visible) {
        node.setVisible(visible);
        node.setManaged(visible);
    }

    private String capitalize(String s) {
        if (s == null || s.isEmpty()) return s;
        return Character.toUpperCase(s.charAt(0)) + s.substring(1).toLowerCase();
    }
}
