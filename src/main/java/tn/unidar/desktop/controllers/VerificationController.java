package tn.unidar.desktop.controllers;

import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.scene.control.*;
import javafx.scene.layout.VBox;
import javafx.stage.FileChooser;
import org.json.JSONObject;
import tn.unidar.desktop.services.ApiClient;
import tn.unidar.desktop.services.AuthService;
import tn.unidar.desktop.services.NavigationService;
import tn.unidar.desktop.utils.I18n;

import java.io.File;
import java.net.URL;
import java.util.ResourceBundle;

public class VerificationController implements Initializable {

    @FXML private VBox   statusCard, uploadForm;
    @FXML private VBox   alertError, alertSuccess;
    @FXML private Label  lblStatusTitle, lblStatusDesc, lblStatusIcon;
    @FXML private Label  badgeStatus;
    @FXML private Label  lblError, lblSuccess;
    @FXML private Label  lblStudentFile, lblCinFile;
    @FXML private Button btnSubmit;

    private File studentIdFile;
    private File cinFile;

    @Override
    public void initialize(URL location, ResourceBundle resources) {
        if (!AuthService.getInstance().isLoggedIn()) {
            NavigationService.getInstance().navigateTo("Login.fxml");
            return;
        }
        checkVerificationStatus();
    }

    private void checkVerificationStatus() {
        new Thread(() -> {
            try {
                JSONObject resp = ApiClient.getInstance().get("/verifications.php");
                Platform.runLater(() -> {
                    JSONObject verifObj = resp.optJSONObject("verification");
                    String status = verifObj != null ? verifObj.optString("status", "none") : "none";
                    if (!"none".equals(status) && !status.isEmpty()) {
                        String message = verifObj.optString("rejection_reason", "");
                        showStatusCard(status, message);
                    }
                });
            } catch (Exception e) { e.printStackTrace(); }
        }).start();
    }

    private void showStatusCard(String status, String message) {
        statusCard.setVisible(true);
        statusCard.setManaged(true);

        switch (status) {
            case "pending" -> {
                lblStatusIcon.setText("⏳");
                lblStatusTitle.setText(I18n.t("verif.pending.title"));
                lblStatusDesc.setText(I18n.t("verif.pending.desc"));
                badgeStatus.setText("PENDING");
                badgeStatus.getStyleClass().setAll("badge", "badge-warning");
                // Hide upload form when pending
                uploadForm.setVisible(false);
                uploadForm.setManaged(false);
            }
            case "approved" -> {
                lblStatusIcon.setText("✅");
                lblStatusTitle.setText(I18n.t("verif.approved.title"));
                lblStatusDesc.setText(I18n.t("verif.approved.desc"));
                badgeStatus.setText("VERIFIED");
                badgeStatus.getStyleClass().setAll("badge", "badge-success");
                uploadForm.setVisible(false);
                uploadForm.setManaged(false);
            }
            case "rejected" -> {
                lblStatusIcon.setText("❌");
                lblStatusTitle.setText(I18n.t("verif.rejected.title"));
                lblStatusDesc.setText(message.isEmpty()
                        ? I18n.t("verif.rejected.desc")
                        : message);
                badgeStatus.setText("REJECTED");
                badgeStatus.getStyleClass().setAll("badge", "badge-error");
            }
        }
    }

    @FXML
    private void selectStudentId() {
        File f = chooseFile("Select Student ID Card");
        if (f != null) {
            studentIdFile = f;
            lblStudentFile.setText("✓  " + f.getName());
            lblStudentFile.setStyle("-fx-text-fill: #16a34a; -fx-font-weight: bold;");
        }
    }

    @FXML
    private void selectCin() {
        File f = chooseFile("Select National ID (CIN)");
        if (f != null) {
            cinFile = f;
            lblCinFile.setText("✓  " + f.getName());
            lblCinFile.setStyle("-fx-text-fill: #16a34a; -fx-font-weight: bold;");
        }
    }

    @FXML
    private void submitVerification() {
        clearAlerts();

        if (studentIdFile == null || cinFile == null) {
            showError(I18n.t("verif.err.files"));
            return;
        }

        // File size check (5MB max)
        if (studentIdFile.length() > 5 * 1024 * 1024 || cinFile.length() > 5 * 1024 * 1024) {
            showError(I18n.t("verif.err.size"));
            return;
        }

        btnSubmit.setDisable(true);
        btnSubmit.setText("Submitting…");

        new Thread(() -> {
            try {
                JSONObject resp = ApiClient.getInstance().uploadFiles(
                        "/verifications.php",
                        new String[]{"student_id_file", "national_id_file"},
                        new File[]{studentIdFile, cinFile}
                );

                Platform.runLater(() -> {
                    btnSubmit.setDisable(false);
                    btnSubmit.setText("Submit for Verification");
                    if (resp.optBoolean("success", false) || resp.has("id")) {
                        showSuccess(I18n.t("verif.success"));
                        showStatusCard("pending", "");
                    } else {
                        showError(resp.optString("error", "Submission failed. Please try again."));
                    }
                });
            } catch (Exception e) {
                e.printStackTrace();
                Platform.runLater(() -> {
                    btnSubmit.setDisable(false);
                    btnSubmit.setText("Submit for Verification");
                    showError(I18n.t("verif.err.conn"));
                });
            }
        }).start();
    }

    private File chooseFile(String title) {
        FileChooser fc = new FileChooser();
        fc.setTitle(title);
        fc.getExtensionFilters().addAll(
                new FileChooser.ExtensionFilter("Images & PDFs", "*.jpg", "*.jpeg", "*.png", "*.pdf")
        );
        return fc.showOpenDialog(null);
    }

    private void showError(String msg) {
        lblError.setText("⚠️  " + msg);
        alertError.setVisible(true);
        alertError.setManaged(true);
    }

    private void showSuccess(String msg) {
        lblSuccess.setText("✅  " + msg);
        alertSuccess.setVisible(true);
        alertSuccess.setManaged(true);
    }

    private void clearAlerts() {
        alertError.setVisible(false);   alertError.setManaged(false);
        alertSuccess.setVisible(false); alertSuccess.setManaged(false);
    }
}
