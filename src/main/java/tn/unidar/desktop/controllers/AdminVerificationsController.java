package tn.unidar.desktop.controllers;

import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.geometry.Pos;
import javafx.scene.control.*;
import javafx.scene.layout.*;
import org.json.JSONArray;
import org.json.JSONObject;
import tn.unidar.desktop.services.ApiClient;
import tn.unidar.desktop.services.AuthService;
import tn.unidar.desktop.services.NavigationService;
import tn.unidar.desktop.utils.I18n;

import java.net.URL;
import java.util.ArrayList;
import java.util.List;
import java.util.ResourceBundle;

public class AdminVerificationsController implements Initializable {

    @FXML private VBox             loadingPane, emptyPane, verifsContainer;
    @FXML private Label            lblCount, lblVerifsTitle, lblVerifsLoading, lblEmptyVerifsTitle, lblEmptyVerifsSub;
    @FXML private ComboBox<String> comboStatus;
    @FXML private Button           btnBack, btnFilter, btnRefresh;

    private List<JSONObject> allVerifs = new ArrayList<>();

    @Override
    public void initialize(URL location, ResourceBundle resources) {
        translateUI();
        if (!AuthService.getInstance().isLoggedIn()) {
            NavigationService.getInstance().navigateTo("Login.fxml");
            return;
        }
        comboStatus.getItems().addAll(I18n.t("admin.verifs.all_statuses"), "pending", "approved", "rejected");
        comboStatus.setValue("pending");
        loadVerifications("pending");
    }

    private void translateUI() {
        if (lblVerifsTitle != null) lblVerifsTitle.setText("🛡️  " + I18n.t("admin.verifs.title"));
        if (btnBack != null) btnBack.setText("← " + I18n.t("admin.back_dashboard"));
        if (comboStatus != null) comboStatus.setPromptText(I18n.t("admin.verifs.all_statuses"));
        if (btnFilter != null) btnFilter.setText(I18n.t("admin.filter"));
        if (btnRefresh != null) btnRefresh.setText(I18n.t("admin.refresh"));
        if (lblVerifsLoading != null) lblVerifsLoading.setText(I18n.t("admin.verifs.loading"));
        if (lblEmptyVerifsTitle != null) lblEmptyVerifsTitle.setText(I18n.t("admin.verifs.none"));
        if (lblEmptyVerifsSub != null) lblEmptyVerifsSub.setText(I18n.t("admin.verifs.none_sub"));
    }

    private void loadVerifications(String status) {
        show(loadingPane, true);
        show(emptyPane, false);
        verifsContainer.getChildren().clear();

        String endpoint = "/verifications.php"
                + (status != null && !status.equals(I18n.t("admin.verifs.all_statuses")) ? "?status=" + status : "");

        new Thread(() -> {
            try {
                JSONObject resp = ApiClient.getInstance().get(endpoint);
                JSONArray verifs = resp.optJSONArray("verifications");
                allVerifs.clear();
                if (verifs != null) {
                    for (int i = 0; i < verifs.length(); i++) allVerifs.add(verifs.getJSONObject(i));
                }
                Platform.runLater(() -> renderVerifs(allVerifs));
            } catch (Exception e) {
                e.printStackTrace();
                Platform.runLater(() -> {
                    show(loadingPane, false);
                    lblCount.setText(I18n.t("common.error"));
                });
            }
        }).start();
    }

    @FXML
    private void handleFilter() {
        String status = comboStatus.getValue();
        loadVerifications(status);
    }

    @FXML
    private void refresh() {
        loadVerifications(comboStatus.getValue());
    }

    private void renderVerifs(List<JSONObject> verifs) {
        show(loadingPane, false);
        verifsContainer.getChildren().clear();

        if (verifs.isEmpty()) {
            show(emptyPane, true);
            lblCount.setText("0 " + I18n.t("admin.stats.verifs"));
            return;
        }
        show(emptyPane, false);
        lblCount.setText(verifs.size() + " " + I18n.t("admin.stats.verifs"));

        for (JSONObject v : verifs) {
            verifsContainer.getChildren().add(buildVerifCard(v));
        }
    }

    private VBox buildVerifCard(JSONObject v) {
        int    id       = v.optInt("id", 0);
        String userName = v.optString("user_name", "User #" + v.optInt("user_id"));
        String email    = v.optString("user_email", "");
        String idType   = v.optString("id_type", "Unknown");
        String status   = v.optString("status", "pending");
        String date     = v.optString("submitted_at", v.optString("created_at", ""))
                           .replace("T", " ").replaceAll("\\..*", "");

        VBox card = new VBox(12);
        card.getStyleClass().add("card");
        card.setStyle("-fx-padding: 20; -fx-background-color: white; -fx-background-radius: 12;" +
                      " -fx-border-color: #e2e8f0; -fx-border-radius: 12; -fx-border-width: 1;");

        // Top row
        HBox top = new HBox(12);
        top.setAlignment(Pos.CENTER_LEFT);

        Label icon = new Label("🛡️");
        icon.setStyle("-fx-font-size: 28px;");

        VBox info = new VBox(4);
        HBox.setHgrow(info, Priority.ALWAYS);
        Label lName = new Label(userName);
        lName.setStyle("-fx-font-weight: bold; -fx-font-size: 15px; -fx-text-fill: #0f172a;");
        Label lEmail = new Label(email);
        lEmail.setStyle("-fx-text-fill: #64748b; -fx-font-size: 12px;");
        info.getChildren().addAll(lName, lEmail);

        String badgeColor = "approved".equals(status) ? "#16a34a" : "rejected".equals(status) ? "#dc2626" : "#d97706";
        String statusKey = "admin.status." + status;
        Label lStatus = new Label(I18n.t(statusKey));
        lStatus.setStyle("-fx-background-color: " + badgeColor + "22; -fx-text-fill: " + badgeColor + ";" +
                         " -fx-font-weight: bold; -fx-font-size: 12px; -fx-background-radius: 8; -fx-padding: 4 10;");

        top.getChildren().addAll(icon, info, lStatus);

        // Details row
        HBox details = new HBox(32);
        details.setAlignment(Pos.CENTER_LEFT);
        details.getChildren().addAll(
                detail(I18n.t("admin.id_type").replace(": %s", ""), idType),
                detail(I18n.t("admin.users.joined"), date.isEmpty() ? "—" : date.substring(0, Math.min(10, date.length())))
        );

        // Document View Buttons
        HBox docButtons = new HBox(10);
        docButtons.setAlignment(Pos.CENTER_LEFT);
        docButtons.setStyle("-fx-padding: 8 0 0 0;");
        
        String studentFile = v.optString("student_id_file", "");
        String nationalFile = v.optString("national_id_file", "");
        
        if (!studentFile.isEmpty()) {
            Button btnViewStudent = new Button("📄  Student ID");
            btnViewStudent.getStyleClass().addAll("btn-ghost", "btn-sm");
            btnViewStudent.setOnAction(e -> tn.unidar.desktop.utils.DocumentViewer.show("Student ID: " + userName, studentFile));
            docButtons.getChildren().add(btnViewStudent);
        }
        
        if (!nationalFile.isEmpty()) {
            Button btnViewCin = new Button("🪪  National ID (CIN)");
            btnViewCin.getStyleClass().addAll("btn-ghost", "btn-sm");
            btnViewCin.setOnAction(e -> tn.unidar.desktop.utils.DocumentViewer.show("National ID: " + userName, nationalFile));
            docButtons.getChildren().add(btnViewCin);
        }

        card.getChildren().addAll(top, details, docButtons);

        // Action buttons (only for pending)
        if ("pending".equals(status)) {
            HBox actions = new HBox(12);
            actions.setAlignment(Pos.CENTER_RIGHT);

            Button btnApprove = new Button("✅  " + I18n.t("admin.approve"));
            btnApprove.getStyleClass().add("btn-primary");
            btnApprove.setOnAction(e -> reviewVerification(id, "approved", btnApprove));

            Button btnReject = new Button("❌  " + I18n.t("admin.reject"));
            btnReject.setStyle("-fx-background-color: #fee2e2; -fx-text-fill: #dc2626;" +
                               " -fx-background-radius: 8; -fx-font-weight: bold; -fx-cursor: hand;");
            btnReject.setOnAction(e -> reviewVerification(id, "rejected", btnReject));

            actions.getChildren().addAll(btnApprove, btnReject);
            card.getChildren().add(actions);
        }

        return card;
    }

    private VBox detail(String label, String value) {
        VBox box = new VBox(3);
        Label lbl = new Label(label);
        lbl.setStyle("-fx-text-fill: #94a3b8; -fx-font-size: 11px; -fx-font-weight: bold;");
        Label val = new Label(value);
        val.setStyle("-fx-text-fill: #334155; -fx-font-size: 13px;");
        box.getChildren().addAll(lbl, val);
        return box;
    }

    private void reviewVerification(int verificationId, String decision, Button btn) {
        btn.setDisable(true);
        btn.setText(I18n.t("admin.processing"));
        new Thread(() -> {
            try {
                JSONObject body = new JSONObject()
                        .put("action", "approved".equals(decision) ? "approve" : "reject");
                JSONObject resp = ApiClient.getInstance().put("/verifications.php?id=" + verificationId, body);
                Platform.runLater(() -> {
                    if (resp.optBoolean("success", false)) {
                        // Reload to refresh the list
                        loadVerifications(comboStatus.getValue());
                    } else {
                        String err = resp.optString("error", "Action failed.");
                        Alert alert = new Alert(Alert.AlertType.ERROR, err, ButtonType.OK);
                        alert.showAndWait();
                        btn.setDisable(false);
                        btn.setText("approved".equals(decision) ? "✅  " + I18n.t("admin.approve") : "❌  " + I18n.t("admin.reject"));
                    }
                });
            } catch (Exception e) {
                e.printStackTrace();
                Platform.runLater(() -> {
                    btn.setDisable(false);
                    btn.setText("approved".equals(decision) ? "✅  " + I18n.t("admin.approve") : "❌  " + I18n.t("admin.reject"));
                });
            }
        }).start();
    }

    @FXML private void goBack() { NavigationService.getInstance().navigateTo("AdminDashboard.fxml"); }

    private void show(javafx.scene.Node n, boolean v) { n.setVisible(v); n.setManaged(v); }

    private String capitalize(String s) {
        if (s == null || s.isEmpty()) return s;
        return Character.toUpperCase(s.charAt(0)) + s.substring(1).toLowerCase();
    }
}
