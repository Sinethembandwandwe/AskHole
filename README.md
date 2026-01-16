# AskHole
AskHole is a web-based dating platform where connections start with questions, not swipes. Users send curated question sets and match based on meaningful answers, blending humor with intentional dating.

## About the Name
The name **AskHole** comes from the idea of a dark, mysterious hole, like the place where people come to ask for their soulmates, much like making wishes to genies or magic trees.  
It’s a little bit cheeky, a little bit hopeful: you toss your questions into the void, knowing you might or might not get an angel popping out to be your perfect match. It is a little dark with a sprinkle of **hope**, because love is often a bit messy, mysterious, and magical all at once.

## Live Demo
Try it yourself here: http://askhole.byethost11.com
 
  **Security Notice:**  
    > This site is hosted on a free hosting plan, so your browser may show a security warning due to lack of HTTPS/SSL certificate.  
    > The site is safe to use, but please proceed with caution if you see any warnings.

## Features
- Send and answer curated question sets.  
- Match based on meaningful answers, not just photos or swipes.  
- Dark humor and playful tone throughout the app.  
- User profiles with gender, pronouns, and location.  
- Block or ignore users without hurting feelings.

## Setup & Development
1. Clone the repo  
2. Copy `config.example.php` to `config.php` and update your database credentials  
3. Run the app locally or deploy it on your hosting

## Database Setup

To run AskHole locally or on your own server, you need to set up the MySQL database.

### 1. Create a new MySQL database
You can create a database called `askhole_db` (or any name you prefer) using your database management tool, e.g., phpMyAdmin or command line.

### 2. Import the database schema
We include the database schema file at `database/askhole.sql` which contains all the table structures.
To import it, run this command in your terminal or command prompt:

```bash
mysql -u your_username -p askhole_db < database/askhole_datbs.sql


## License
This project is licensed under the MIT License.
