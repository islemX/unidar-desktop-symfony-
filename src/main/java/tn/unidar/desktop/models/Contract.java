package tn.unidar.desktop.models;

public class Contract {
    private int    id;
    private int    listingId;
    private String listingTitle;
    private int    studentId;
    private String studentName;
    private int    ownerId;
    private double monthlyRent;
    private String startDate;
    private String endDate;
    private String status;
    private String createdAt;

    public Contract(int id) { this.id = id; }

    public int    getId()              { return id; }
    public int    getListingId()       { return listingId; }
    public void   setListingId(int l)  { this.listingId = l; }
    public String getListingTitle()    { return listingTitle; }
    public void   setListingTitle(String t) { this.listingTitle = t; }
    public int    getStudentId()       { return studentId; }
    public void   setStudentId(int s)  { this.studentId = s; }
    public String getStudentName()     { return studentName; }
    public void   setStudentName(String n) { this.studentName = n; }
    public int    getOwnerId()         { return ownerId; }
    public void   setOwnerId(int o)    { this.ownerId = o; }
    public double getMonthlyRent()     { return monthlyRent; }
    public void   setMonthlyRent(double r) { this.monthlyRent = r; }
    public String getStartDate()       { return startDate; }
    public void   setStartDate(String d) { this.startDate = d; }
    public String getEndDate()         { return endDate; }
    public void   setEndDate(String d) { this.endDate = d; }
    public String getStatus()          { return status; }
    public void   setStatus(String s)  { this.status = s; }
    public String getCreatedAt()       { return createdAt; }
    public void   setCreatedAt(String c) { this.createdAt = c; }
}
