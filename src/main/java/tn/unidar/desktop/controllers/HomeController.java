package tn.unidar.desktop.controllers;

import javafx.fxml.FXML;
import tn.unidar.desktop.services.NavigationService;

public class HomeController {

    @FXML private void gotoListings()  { NavigationService.getInstance().navigateTo("Listings.fxml"); }
    @FXML private void gotoRoommates() { NavigationService.getInstance().navigateTo("Roommates.fxml"); }
    @FXML private void gotoRegister()  { NavigationService.getInstance().navigateTo("Register.fxml"); }
}
