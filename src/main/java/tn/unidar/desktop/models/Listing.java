package tn.unidar.desktop.models;
import java.util.ArrayList;
import java.util.List;

public class Listing {
    private int id;
    private String title;
    private String description;
    private String address;
    private double price;
    private int bedrooms;
    private int bathrooms;
    private int capacity;
    private String propertyType;
    private String genderPreference;
    private String thumbnail;
    private boolean isSaved;
    private boolean isAvailable;
    private int ownerId;
    private String ownerName;
    private String createdAt;
    private List<String> images = new ArrayList<>();
    private List<String> imageUrls = new ArrayList<>();

    public Listing(int id, String title, String address, double price) {
        this.id      = id;
        this.title   = title;
        this.address = address;
        this.price   = price;
    }

    public int    getId()              { return id; }
    public String getTitle()           { return title; }
    public String getAddress()         { return address; }
    public double getPrice()           { return price; }
    public int    getBedrooms()        { return bedrooms; }
    public void   setBedrooms(int b)   { this.bedrooms = b; }
    public int    getBathrooms()       { return bathrooms; }
    public void   setBathrooms(int b)  { this.bathrooms = b; }
    public int    getCapacity()        { return capacity; }
    public void   setCapacity(int c)   { this.capacity = c; }
    public String getPropertyType()    { return propertyType; }
    public void   setPropertyType(String t) { this.propertyType = t; }
    public String getGenderPreference()     { return genderPreference; }
    public void   setGenderPreference(String g) { this.genderPreference = g; }
    public String getThumbnail()       { return thumbnail; }
    public void   setThumbnail(String t) { this.thumbnail = t; }
    public boolean isSaved()           { return isSaved; }
    public void   setSaved(boolean s)  { this.isSaved = s; }
    public boolean isAvailable()       { return isAvailable; }
    public void   setAvailable(boolean a) { this.isAvailable = a; }
    public int    getOwnerId()         { return ownerId; }
    public void   setOwnerId(int o)    { this.ownerId = o; }
    public String getOwnerName()       { return ownerName; }
    public void   setOwnerName(String n) { this.ownerName = n; }
    public String getDescription()     { return description; }
    public void   setDescription(String d) { this.description = d; }
    public String getCreatedAt()       { return createdAt; }
    public void   setCreatedAt(String c) { this.createdAt = c; }

    public List<String> getImages() { return images; }
    public void setImages(List<String> images) { this.images = images; }
    
    public List<String> getImageUrls() { return imageUrls; }
    public void setImageUrls(List<String> imageUrls) { this.imageUrls = imageUrls; }
}
