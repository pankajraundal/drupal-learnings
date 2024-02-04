Assignment#01:
Assume you are tasked to develop a portal for a USA based Health Insurance company where the end users submit their medical claims and view their medical claims in a sophisticated manner.  The platform is expected to handle a high volume of concurrent users, multi browser compatible and mobile responsive. UI is left to your best imagination for a better user experience.
Requirements:
1.	Create a page where there are 2 tabs for ‘View Claims’ and ‘Submit Claims’.
2.	Upon choosing Submit claims, a form to open for user to submit their claims with the fields in Appendix 01. Choose the best data type for these fields. Follow the validation rule as given.
3.	Store all date in a local JSON file through an RestAPI
4.	User should be able to submit the claims successfully. Insert the data as given in Appendix 02.
5.	Upon choosing the View claims, user should land on a screen where the claims be filtered by the below parameters.
a.	Patient name – Use Drop down
b.	Claims number – Auto complete field.
c.	Service Type
d.	Start Date – Use calendar to choose the data. Also, user should be able to type the date. User should be restricted to choose dates within the last 18 months.
e.	End Date. This is start date and end date are used to filter by the submission date.
6.	When filter is applied, data should be retrieved through a RestAPI and displayed in a table format.
7.	Introduce pagination for every 5 records.
8.	Export the result to a csv file/excel/notepad. Choose any one file format though excel is preferred.

Appendix 01:
Field	Validation Rule
Claims Number	Auto Generate a 9-digit number. Show the success message with this number when the claim is submitted.
Patient Name	Free text. Input should be Alphabetical.
Service Type	Only accept 'Medical, Dental'
Provider Name	Free text.
Claims Value	Any numeric prefixed '$'
Submission Date 	Capture System Date and Time. If user wants to change, there should be an option.
