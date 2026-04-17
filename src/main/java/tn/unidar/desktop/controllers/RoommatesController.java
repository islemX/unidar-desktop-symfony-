package tn.unidar.desktop.controllers;

import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.scene.layout.*;
import javafx.stage.Modality;
import javafx.stage.Stage;
import org.json.JSONArray;
import org.json.JSONObject;
import tn.unidar.desktop.services.ApiClient;
import tn.unidar.desktop.services.AuthService;
import tn.unidar.desktop.services.NavigationService;

import java.net.URL;
import java.util.HashMap;
import java.util.Map;
import java.util.ResourceBundle;

public class RoommatesController implements Initializable {

    @FXML private FlowPane matchesGrid;
    @FXML private VBox loadingPane, emptyPane;
    @FXML private HBox prefSummaryBar;
    @FXML private Label lblPrefSummary;

    private Map<String, String> currentPrefs = new HashMap<>();

    @Override
    public void initialize(URL location, ResourceBundle resources) {
        if (!AuthService.getInstance().isLoggedIn()) {
            showEmpty(true);
            return;
        }
        loadMatches();
    }

    private void loadMatches() {
        setLoading(true);
        new Thread(() -> {
            try {
                StringBuilder url = new StringBuilder("/roommates.php?matches=1&");
                currentPrefs.forEach((k, v) -> {
                    String key = k;
                    // Map Java keys to PHP keys if needed
                    if (k.equals("min_budget")) key = "budget_min";
                    if (k.equals("max_budget")) key = "budget_max";
                    if (k.equals("cleanliness")) key = "cleanliness_level";
                    if (k.equals("noise_preference")) key = "noise_tolerance";
                    if (k.equals("smoking")) key = "smoking_preference";
                    if (k.equals("guests_policy")) key = "guests";
                    if (k.equals("pets_policy")) key = "pets";
                    
                    try {
                        url.append(key).append("=").append(java.net.URLEncoder.encode(v, "UTF-8")).append("&");
                    } catch (Exception ex) {}
                });

                JSONObject resp = ApiClient.getInstance().get(url.toString());
                JSONArray matches = resp.optJSONArray("matches");

                Platform.runLater(() -> {
                    setLoading(false);
                    if (matches == null || matches.isEmpty()) {
                        showEmpty(true);
                        return;
                    }
                    showEmpty(false);
                    renderMatches(matches);
                });
            } catch (Exception e) {
                e.printStackTrace();
                Platform.runLater(() -> { setLoading(false); showEmpty(true); });
            }
        }).start();
    }

    private void renderMatches(JSONArray matches) {
        matchesGrid.getChildren().clear();
        for (int i = 0; i < matches.length(); i++) {
            JSONObject m = matches.getJSONObject(i);
            matchesGrid.getChildren().add(buildMatchCard(m));
        }
    }

    private VBox buildMatchCard(JSONObject m) {
        String name   = m.optString("full_name", "Student");
        String univ   = m.optString("university", "University");
        int    score  = m.optInt("compatibility_score", 75);
        int    uid    = m.optInt("id", 0);

        VBox card = new VBox(12);
        card.getStyleClass().add("roommate-card");
        card.setAlignment(Pos.CENTER);
        card.setPrefWidth(260);

        // Avatar
        Label avatar = new Label(name.isEmpty() ? "U" : String.valueOf(name.charAt(0)).toUpperCase());
        avatar.getStyleClass().add("roommate-avatar");

        // Name & meta
        Label lName = new Label(name);
        lName.getStyleClass().add("roommate-name");
        Label lUniv = new Label("🎓 " + univ);
        lUniv.getStyleClass().add("roommate-meta");

        // Compatibility score
        VBox scoreBox = new VBox(2);
        scoreBox.setAlignment(Pos.CENTER);
        scoreBox.getStyleClass().add("glow-card");
        scoreBox.setStyle("-fx-padding: 10 20 10 20; -fx-background-color: -fx-color-surface-50;");
        Label scoreNum = new Label(score + "%");
        scoreNum.getStyleClass().add("compatibility-text");
        Label scoreLbl = new Label("Compatibility");
        scoreLbl.getStyleClass().add("compatibility-label");
        scoreBox.getChildren().addAll(scoreNum, scoreLbl);

        // Preference badges
        FlowPane prefChips = new FlowPane(6, 6);
        prefChips.setAlignment(Pos.CENTER);
        String[] prefs = {
            m.optString("cleanliness", ""),
            m.optString("sleep_schedule", ""),
            m.optString("noise_preference", "")
        };
        for (String p : prefs) {
            if (!p.isEmpty()) {
                Label chip = new Label(formatPref(p));
                chip.getStyleClass().add("listing-meta");
                prefChips.getChildren().add(chip);
            }
        }

        Button msgBtn = new Button("💬  Message");
        msgBtn.getStyleClass().addAll("btn-primary", "btn-sm");
        msgBtn.setMaxWidth(Double.MAX_VALUE);
        msgBtn.setOnAction(e -> startChat(uid, name));

        card.getChildren().addAll(avatar, lName, lUniv, scoreBox, prefChips, msgBtn);
        return card;
    }

    @FXML
    private void openPreferences() {
        if (!AuthService.getInstance().isLoggedIn()) {
            NavigationService.getInstance().navigateTo("Login.fxml");
            return;
        }

        Stage dialog = new Stage();
        dialog.initModality(Modality.APPLICATION_MODAL);
        dialog.setTitle("Roommate Preferences");
        dialog.setResizable(false);

        // ── Root ──
        VBox dialogRoot = new VBox(0);
        dialogRoot.getStyleClass().add("pref-dialog-root");

        // ── Header (indigo gradient) ──
        VBox header = new VBox(6);
        header.getStyleClass().add("pref-header");
        Label headerTitle = new Label("⚙️  Your Roommate Preferences");
        headerTitle.getStyleClass().add("pref-header-title");
        Label headerSub = new Label("Customize to find your perfect match");
        headerSub.getStyleClass().add("pref-header-sub");
        header.getChildren().addAll(headerTitle, headerSub);

        // ── Scrollable Body ──
        ScrollPane scroll = new ScrollPane();
        scroll.setFitToWidth(true);
        scroll.setStyle("-fx-background-color: transparent; -fx-background: transparent;");

        VBox body = new VBox(18);
        body.getStyleClass().add("pref-body");
        body.setPrefWidth(500);

        // ── Section: Budget ──
        body.getChildren().add(buildPrefLabel("💰  Budget (TND/month)"));
        HBox budgetRow = new HBox(12);
        TextField minBudget = prefField("Min budget", "min_budget");
        TextField maxBudget = prefField("Max budget", "max_budget");
        budgetRow.getChildren().addAll(minBudget, maxBudget);
        body.getChildren().add(budgetRow);

        // ── Separator ──
        body.getChildren().add(new Separator());

        // ── Section: Cleanliness ──
        body.getChildren().add(buildPrefLabel("🧹  Cleanliness Level"));
        body.getChildren().add(buildChipGroup(new String[]{"relaxed", "moderate", "clean", "very_clean"}, "cleanliness"));

        // ── Section: Sleep Schedule ──
        body.getChildren().add(buildPrefLabel("🌙  Sleep Schedule"));
        body.getChildren().add(buildChipGroup(new String[]{"early_riser", "normal", "night_owl"}, "sleep_schedule"));

        // ── Section: Gender Preference ──
        body.getChildren().add(buildPrefLabel("👤  Gender Preference"));
        body.getChildren().add(buildChipGroup(new String[]{"male", "female", "no_preference"}, "gender"));

        // ── Section: Smoking ──
        body.getChildren().add(buildPrefLabel("🚬  Smoking"));
        body.getChildren().add(buildChipGroup(new String[]{"non_smoker", "smoker_ok"}, "smoking"));

        // ── Section: Noise Tolerance ──
        body.getChildren().add(buildPrefLabel("🔊  Noise Tolerance"));
        body.getChildren().add(buildChipGroup(new String[]{"quiet", "moderate", "social"}, "noise_preference"));

        // ── Section: Guests Policy ──
        body.getChildren().add(buildPrefLabel("🧑‍🤝‍🧑  Guests Policy"));
        body.getChildren().add(buildChipGroup(new String[]{"no_guests", "occasional"}, "guests_policy"));

        // ── Section: Pets ──
        body.getChildren().add(buildPrefLabel("🐾  Pets"));
        body.getChildren().add(buildChipGroup(new String[]{"no_pets", "pets_ok"}, "pets_policy"));

        // ── Submit Button ──
        Button submitBtn = new Button("🔍  Find My Matches");
        submitBtn.getStyleClass().add("pref-submit-btn");
        submitBtn.setMaxWidth(Double.MAX_VALUE);
        submitBtn.setOnAction(e -> {
            JSONObject body2 = new JSONObject();
            if (currentPrefs.containsKey("min_budget")) body2.put("budget_min", currentPrefs.get("min_budget"));
            if (currentPrefs.containsKey("max_budget")) body2.put("budget_max", currentPrefs.get("max_budget"));
            if (currentPrefs.containsKey("cleanliness")) body2.put("cleanliness_level", currentPrefs.get("cleanliness"));
            if (currentPrefs.containsKey("sleep_schedule")) body2.put("sleep_schedule", currentPrefs.get("sleep_schedule"));
            if (currentPrefs.containsKey("gender")) body2.put("gender_preference", currentPrefs.get("gender"));
            if (currentPrefs.containsKey("smoking")) body2.put("smoking_preference", currentPrefs.get("smoking"));
            if (currentPrefs.containsKey("noise_preference")) body2.put("noise_tolerance", currentPrefs.get("noise_preference"));
            if (currentPrefs.containsKey("guests_policy")) body2.put("guests", currentPrefs.get("guests_policy"));
            if (currentPrefs.containsKey("pets_policy")) body2.put("pets", currentPrefs.get("pets_policy"));

            new Thread(() -> {
                try { ApiClient.getInstance().post("/roommates.php", body2); }
                catch (Exception ex) { ex.printStackTrace(); }
            }).start();

            String summary = buildPrefSummary();
            prefSummaryBar.setVisible(true);
            prefSummaryBar.setManaged(true);
            lblPrefSummary.setText("Showing matches for: " + summary);

            dialog.close();
            loadMatches();
        });
        body.getChildren().add(submitBtn);

        scroll.setContent(body);
        dialogRoot.getChildren().addAll(header, scroll);

        Scene scene = new Scene(dialogRoot, 560, 680);
        scene.getStylesheets().add(getClass().getResource("/css/unidar-pages.css").toExternalForm());
        dialog.setScene(scene);
        dialog.show();
    }

    private Label buildPrefLabel(String text) {
        Label l = new Label(text);
        l.getStyleClass().add("pref-section-label");
        return l;
    }

    private TextField prefField(String prompt, String prefKey) {
        TextField tf = new TextField();
        tf.setPromptText(prompt);
        tf.getStyleClass().add("pref-budget-field");
        HBox.setHgrow(tf, Priority.ALWAYS);
        if (currentPrefs.containsKey(prefKey)) tf.setText(String.valueOf(currentPrefs.get(prefKey)));
        tf.textProperty().addListener((obs, old, val) -> {
            if (!val.isEmpty()) currentPrefs.put(prefKey, val);
        });
        return tf;
    }

    private TextField styledField(String prompt) {
        TextField tf = new TextField();
        tf.setPromptText(prompt);
        tf.getStyleClass().add("form-input");
        tf.setPrefHeight(40);
        HBox.setHgrow(tf, Priority.ALWAYS);
        return tf;
    }


    private HBox buildChipGroup(String[] options, String prefKey) {
        HBox row = new HBox(8);
        row.setAlignment(Pos.CENTER_LEFT);
        ToggleGroup tg = new ToggleGroup();
        for (String opt : options) {
            ToggleButton chip = new ToggleButton(formatPref(opt));
            chip.setToggleGroup(tg);
            chip.getStyleClass().add("toggle-chip");
            chip.selectedProperty().addListener((obs, old, selected) -> {
                if (selected) {
                    currentPrefs.put(prefKey, opt);
                }
            });
            if (currentPrefs.containsKey(prefKey) && currentPrefs.get(prefKey).equals(opt)) {
                chip.setSelected(true);
            }
            row.getChildren().add(chip);
        }
        return row;
    }

    private void startChat(int userId, String name) {
        if (!AuthService.getInstance().isLoggedIn()) {
            NavigationService.getInstance().navigateTo("Login.fxml");
            return;
        }
        new Thread(() -> {
            try {
                JSONObject body = new JSONObject().put("recipient_id", userId);
                ApiClient.getInstance().post("/conversations.php?action=create", body);
                Platform.runLater(() -> NavigationService.getInstance().navigateTo("Messages.fxml", userId));
            } catch (Exception e) {
                e.printStackTrace();
                Platform.runLater(() -> NavigationService.getInstance().navigateTo("Messages.fxml"));
            }
        }).start();
    }

    private String buildPrefSummary() {
        if (currentPrefs.isEmpty()) return "default preferences";
        StringBuilder sb = new StringBuilder();
        currentPrefs.forEach((k, v) -> sb.append(formatPref(v)).append(", "));
        return sb.length() > 2 ? sb.substring(0, sb.length() - 2) : sb.toString();
    }

    private String formatPref(String s) {
        if (s == null || s.isEmpty()) return "";
        return s.replace("_", " ");
    }

    private void setLoading(boolean loading) {
        loadingPane.setVisible(loading);
        loadingPane.setManaged(loading);
    }

    private void showEmpty(boolean empty) {
        emptyPane.setVisible(empty);
        emptyPane.setManaged(empty);
        matchesGrid.setVisible(!empty);
        matchesGrid.setManaged(!empty);
    }
}
