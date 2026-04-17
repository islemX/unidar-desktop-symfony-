package tn.unidar.desktop.controllers;

import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.scene.control.*;
import javafx.scene.layout.VBox;
import org.json.JSONObject;
import tn.unidar.desktop.services.ApiClient;
import tn.unidar.desktop.services.NavigationService;
import tn.unidar.desktop.utils.I18n;

import java.net.URL;
import java.util.ResourceBundle;

public class RegisterController implements Initializable {

    @FXML private TextField     txtFullName;
    @FXML private TextField     txtEmail;
    @FXML private TextField     txtPhone;
    @FXML private PasswordField txtPassword;
    @FXML private PasswordField txtConfirmPassword;
    @FXML private ToggleButton  btnStudent, btnOwner;
    @FXML private ComboBox<String> comboGender;
    @FXML private ComboBox<String> comboCity;
    @FXML private ComboBox<String> comboUniversity;
    @FXML private VBox           uniSection;
    @FXML private VBox           alertError;
    @FXML private Label          lblError;
    @FXML private Button         btnRegister;

    private String selectedRole = "student";


    @Override
    public void initialize(URL location, ResourceBundle resources) {
        // Gender options
        comboGender.getItems().addAll(
            I18n.t("reg.gender.male"), 
            I18n.t("reg.gender.female"), 
            I18n.t("reg.gender.other")
        );

        // Tunisian cities
        comboCity.getItems().addAll(
            "Tunis", "Sfax", "Sousse", "Kairouan", "Bizerte",
            "Gabès", "Ariana", "Gafsa", "Monastir", "Nabeul",
            "Ben Arous", "Kasserine", "Médenine", "Tataouine", "Béja",
            "Jendouba", "Mahdia", "Sidi Bouzid", "Siliana", "Zaghouan", "Tozeur", "Kebili"
        );

        // Tunisian universities
        comboUniversity.getItems().addAll(
            "University of Tunis El Manar", "University of Carthage",
            "University of Sfax", "University of Sousse",
            "University of Monastir", "University of Gafsa",
            "University of Gabès", "University of Jendouba",
            "University of Kairouan", "University of Manouba",
            "University of La Manouba", "University of 7th November at Carthage",
            "ESPRIT", "ISAMM", "ISITCOM", "IHEC", "ISG", "FSEG Tunis",
            "ENSI", "ENIT", "SUP'COM", "ISSAT"
        );

    }


    @FXML
    private void selectStudent() {
        selectedRole = "student";
        uniSection.setVisible(true);
        uniSection.setManaged(true);
        btnStudent.setStyle("-fx-background-color: #4f46e5; -fx-text-fill: white; -fx-background-radius: 8; -fx-padding: 10 0; -fx-font-weight: bold; -fx-cursor: hand;");
        btnOwner.setStyle("-fx-background-color: #f1f5f9; -fx-text-fill: #64748b; -fx-background-radius: 8; -fx-padding: 10 0; -fx-cursor: hand;");
    }

    @FXML
    private void selectOwner() {
        selectedRole = "owner";
        uniSection.setVisible(false);
        uniSection.setManaged(false);
        btnOwner.setStyle("-fx-background-color: #4f46e5; -fx-text-fill: white; -fx-background-radius: 8; -fx-padding: 10 0; -fx-font-weight: bold; -fx-cursor: hand;");
        btnStudent.setStyle("-fx-background-color: #f1f5f9; -fx-text-fill: #64748b; -fx-background-radius: 8; -fx-padding: 10 0; -fx-cursor: hand;");
    }

    @FXML
    private void handleRegister() {
        clearError();

        String name     = txtFullName.getText().trim();
        String email    = txtEmail.getText().trim();
        String phone    = txtPhone.getText().trim();
        String pass     = txtPassword.getText();
        String conf     = txtConfirmPassword.getText();
        String gender   = comboGender.getValue();
        String city     = comboCity.getValue();
        String uni      = comboUniversity.getValue();

        if (name.isEmpty() || email.isEmpty() || pass.isEmpty()) {
            showError(I18n.t("reg.err.required"));
            return;
        }
        if (gender == null) {
            showError(I18n.t("reg.err.gender"));
            return;
        }
        if (!pass.equals(conf)) {
            showError(I18n.t("reg.err.mismatch"));
            return;
        }
        if (pass.length() < 8) {
            showError(I18n.t("reg.err.length"));
            return;
        }

        btnRegister.setDisable(true);
        btnRegister.setText(I18n.t("common.loading"));

        new Thread(() -> {
            try {
                JSONObject body = new JSONObject()
                        .put("full_name", name)
                        .put("email",     email)
                        .put("password",  pass)
                        .put("role",      selectedRole)
                        .put("gender",    gender.equals(I18n.t("reg.gender.other")) ? null : gender.toLowerCase());
                if (!phone.isEmpty())    body.put("phone",      phone);
                if (city  != null)       body.put("city",       city);
                if (uni   != null)       body.put("university", uni);

                JSONObject resp = ApiClient.getInstance().post("/auth.php?action=register", body);

                Platform.runLater(() -> {
                    btnRegister.setDisable(false);
                    btnRegister.setText(I18n.t("reg.create"));

                    if (resp.optBoolean("success", false) || resp.has("id")) {
                        Alert alert = new Alert(Alert.AlertType.INFORMATION,
                                I18n.t("reg.success.msg"),
                                ButtonType.OK);
                        alert.setTitle(I18n.t("reg.success.title"));
                        alert.setHeaderText(I18n.t("reg.success.welcome"));
                        alert.showAndWait();
                        NavigationService.getInstance().navigateTo("Login.fxml");
                    } else {
                        showError(resp.optString("error", "Registration failed."));
                    }
                });
            } catch (Exception e) {
                e.printStackTrace();
                Platform.runLater(() -> {
                    btnRegister.setDisable(false);
                    btnRegister.setText(I18n.t("reg.create"));
                    showError(I18n.t("reg.err.conn"));
                });
            }
        }).start();
    }

    @FXML private void gotoLogin() { NavigationService.getInstance().navigateTo("Login.fxml"); }

    private void showError(String msg) {
        lblError.setText("⚠️  " + msg);
        alertError.setVisible(true);
        alertError.setManaged(true);
    }

    private void clearError() {
        alertError.setVisible(false);
        alertError.setManaged(false);
    }
}
