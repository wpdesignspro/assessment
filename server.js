const express = require('express');
const sqlite3 = require('sqlite3').verbose();
const multer = require('multer');
const path = require('path');
const fs = require('fs');
const cors = require('cors');
const dotenv = require('dotenv');
const bcrypt = require('bcryptjs');
const nodemailer = require('nodemailer');

dotenv.config();

const app = express();
const PORT = process.env.PORT || 3000;

// Middleware
app.use(cors());
app.use(express.json({ limit: '50mb' }));
app.use(express.urlencoded({ extended: true, limit: '50mb' }));

// Serve static files
app.use('/uploads', express.static('uploads'));

// Create uploads directory if it doesn't exist
if (!fs.existsSync('./uploads')) {
    fs.mkdirSync('./uploads');
}

// Configure multer for file uploads
const storage = multer.diskStorage({
    destination: (req, file, cb) => {
        cb(null, 'uploads/');
    },
    filename: (req, file, cb) => {
        const uniqueSuffix = Date.now() + '-' + Math.round(Math.random() * 1E9);
        cb(null, file.fieldname + '-' + uniqueSuffix + path.extname(file.originalname));
    }
});
const upload = multer({ storage: storage });

// SQLite connection
const db = new sqlite3.Database('./submissions.db', (err) => {
    if (err) {
        console.error('Database connection failed:', err);
        return;
    }
    console.log('Connected to SQLite database');
    
    // Create table if it doesn't exist
    const createTableQuery = `
        CREATE TABLE IF NOT EXISTS submissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            school_name TEXT NOT NULL,
            contact_person TEXT NOT NULL,
            phone TEXT NOT NULL,
            email TEXT NOT NULL,
            dedicated_building TEXT NOT NULL,
            facility_type TEXT NOT NULL,
            status TEXT NOT NULL,
            health_state TEXT NOT NULL,
            gross_floor_area REAL,
            meets_minimum TEXT,
            total_size REAL,
            num_floors TEXT,
            location TEXT,
            computer_system TEXT,
            num_computers INTEGER,
            spec_meet TEXT,
            networking TEXT,
            internet_speed TEXT,
            num_exits TEXT,
            conveniences TEXT,
            convenience_attached TEXT,
            furnished TEXT,
            furniture_list TEXT,
            video_files TEXT,
            picture_files TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    `;
    
    db.run(createTableQuery, (err) => {
        if (err) {
            console.error('Error creating table:', err);
        } else {
            console.log('Table \'submissions\' ready');
        }
    });
});

// Create transporter for nodemailer
const transporter = nodemailer.createTransport({
    service: 'gmail',
    auth: {
        user: process.env.EMAIL_USER,
        pass: process.env.EMAIL_PASS
    }
});

// API Routes

// Submit form
app.post('/api/submit-form', upload.fields([
    { name: 'videos', maxCount: 10 },
    { name: 'pictures', maxCount: 10 }
]), (req, res) => {
    try {
        // Get file paths
        const videoFiles = req.files['videos'] ? req.files['videos'].map(file => file.filename) : [];
        const pictureFiles = req.files['pictures'] ? req.files['pictures'].map(file => file.filename) : [];

        // Insert into database
        const query = `
            INSERT INTO submissions (
                school_name, contact_person, phone, email, dedicated_building, 
                facility_type, status, health_state, gross_floor_area, meets_minimum, 
                total_size, num_floors, location, computer_system, num_computers, 
                spec_meet, networking, internet_speed, num_exits, conveniences, 
                convenience_attached, furnished, furniture_list, video_files, picture_files
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        `;

        const values = [
            req.body.schoolName,
            req.body.contactPerson,
            req.body.phone,
            req.body.email,
            req.body.dedicatedBuilding,
            req.body.facilityType,
            req.body.status,
            req.body.healthState,
            req.body.grossFloorArea,
            req.body.meetsMinimum,
            req.body.totalSize,
            req.body.numFloors,
            req.body.location,
            req.body.computerSystem,
            req.body.numComputers,
            req.body.specMeet,
            req.body.networking,
            req.body.internetSpeed,
            req.body.numExits,
            req.body.conveniences ? req.body.conveniences.join(',') : '',
            req.body.convenienceAttached,
            req.body.furnished,
            req.body.furnitureList,
            JSON.stringify(videoFiles),
            JSON.stringify(pictureFiles)
        ];

        db.run(query, values, function(err) {
            if (err) {
                console.error('Error inserting data:', err);
                return res.status(500).json({ error: 'Failed to save form data' });
            }

            // Send email with links to uploaded files
            const mailOptions = {
                from: process.env.EMAIL_USER,
                to: req.body.email,
                subject: 'Your Form Submission - Media Links',
                html: `
                    <h2>Your form has been submitted successfully!</h2>
                    <p>Thank you for submitting the ICT infrastructure form.</p>
                    <p>You can access your uploaded media files using the links below:</p>
                    ${videoFiles.length > 0 ? `
                        <h3>Video Files:</h3>
                        <ul>
                            ${videoFiles.map(file => `<li><a href="${process.env.HOST_URL || 'http://localhost:3000'}/uploads/${file}">${file}</a></li>`).join('')}
                        </ul>
                    ` : ''}
                    ${pictureFiles.length > 0 ? `
                        <h3>Picture Files:</h3>
                        <ul>
                            ${pictureFiles.map(file => `<li><a href="${process.env.HOST_URL || 'http://localhost:3000'}/uploads/${file}">${file}</a></li>`).join('')}
                        </ul>
                    ` : ''}
                `
            };

            transporter.sendMail(mailOptions, (error, info) => {
                if (error) {
                    console.error('Error sending email:', error);
                } else {
                    console.log('Email sent: ' + info.response);
                }
            });

            res.status(200).json({ message: 'Form submitted successfully!' });
        });
    } catch (error) {
        console.error('Error processing form:', error);
        res.status(500).json({ error: 'Internal server error' });
    }
});

// Middleware to verify basic auth
function verifyAuth(role) {
    return (req, res, next) => {
        const authHeader = req.headers.authorization;
        
        if (!authHeader || !authHeader.startsWith('Basic ')) {
            return res.status(401).json({ error: 'Missing authorization header' });
        }
        
        const base64Credentials = authHeader.split(' ')[1];
        const credentials = Buffer.from(base64Credentials, 'base64').toString('ascii');
        const [username, password] = credentials.split(':');
        
        let validUser = false;
        if (role === 'admin') {
            validUser = (username === process.env.ADMIN_USERNAME && password === process.env.ADMIN_PASSWORD);
        } else if (role === 'review') {
            validUser = (username === process.env.REVIEW_USERNAME && password === process.env.REVIEW_PASSWORD);
        }
        
        if (validUser) {
            next();
        } else {
            res.status(401).json({ error: 'Invalid credentials' });
        }
    };
}

// Admin login route (for testing purposes)
app.post('/api/admin/login', (req, res) => {
    const { username, password } = req.body;
    
    if (username === process.env.ADMIN_USERNAME && password === process.env.ADMIN_PASSWORD) {
        res.json({ success: true, role: 'admin' });
    } else {
        res.status(401).json({ success: false, message: 'Invalid credentials' });
    }
});

// Review login route (for testing purposes)
app.post('/api/review/login', (req, res) => {
    const { username, password } = req.body;
    
    if (username === process.env.REVIEW_USERNAME && password === process.env.REVIEW_PASSWORD) {
        res.json({ success: true, role: 'review' });
    } else {
        res.status(401).json({ success: false, message: 'Invalid credentials' });
    }
});

// Get all submissions (admin only)
app.get('/api/submissions', verifyAuth('admin'), (req, res) => {
    const query = 'SELECT * FROM submissions ORDER BY created_at DESC';
    
    db.all(query, [], (err, results) => {
        if (err) {
            console.error('Error fetching submissions:', err);
            return res.status(500).json({ error: 'Failed to fetch submissions' });
        }
        
        res.json(results);
    });
});

// Get submissions without media (review only)
app.get('/api/submissions-review', verifyAuth('review'), (req, res) => {
    const query = 'SELECT id, school_name, contact_person, phone, email, dedicated_building, facility_type, status, health_state, gross_floor_area, meets_minimum, total_size, num_floors, location, computer_system, num_computers, spec_meet, networking, internet_speed, num_exits, conveniences, convenience_attached, furnished, furniture_list, created_at FROM submissions ORDER BY created_at DESC';
    
    db.all(query, [], (err, results) => {
        if (err) {
            console.error('Error fetching submissions:', err);
            return res.status(500).json({ error: 'Failed to fetch submissions' });
        }
        
        res.json(results);
    });
});

// Export to CSV route (admin only)
app.get('/api/export-csv', verifyAuth('admin'), (req, res) => {
    const query = 'SELECT * FROM submissions ORDER BY created_at DESC';
    
    db.all(query, [], (err, results) => {
        if (err) {
            console.error('Error exporting to CSV:', err);
            return res.status(500).json({ error: 'Failed to export data' });
        }
        
        // Convert to CSV
        let csv = '';
        if (results.length > 0) {
            // Add headers
            csv += Object.keys(results[0]).join(',') + '\n';
            
            // Add rows
            results.forEach(row => {
                csv += Object.values(row).map(field => {
                    // Handle fields with commas by wrapping in quotes
                    if (typeof field === 'string' && field.includes(',')) {
                        return `"${field}"`;
                    }
                    return field;
                }).join(',') + '\n';
            });
        }
        
        res.setHeader('Content-Type', 'text/csv');
        res.setHeader('Content-Disposition', 'attachment; filename=submissions.csv');
        res.send(csv);
    });
});

// Export to CSV route (review only - without media)
app.get('/api/export-csv-review', verifyAuth('review'), (req, res) => {
    const query = 'SELECT id, school_name, contact_person, phone, email, dedicated_building, facility_type, status, health_state, gross_floor_area, meets_minimum, total_size, num_floors, location, computer_system, num_computers, spec_meet, networking, internet_speed, num_exits, conveniences, convenience_attached, furnished, furniture_list, created_at FROM submissions ORDER BY created_at DESC';
    
    db.all(query, [], (err, results) => {
        if (err) {
            console.error('Error exporting to CSV:', err);
            return res.status(500).json({ error: 'Failed to export data' });
        }
        
        // Convert to CSV
        let csv = '';
        if (results.length > 0) {
            // Add headers
            csv += Object.keys(results[0]).join(',') + '\n';
            
            // Add rows
            results.forEach(row => {
                csv += Object.values(row).map(field => {
                    // Handle fields with commas by wrapping in quotes
                    if (typeof field === 'string' && field.includes(',')) {
                        return `"${field}"`;
                    }
                    return field;
                }).join(',') + '\n';
            });
        }
        
        res.setHeader('Content-Type', 'text/csv');
        res.setHeader('Content-Disposition', 'attachment; filename=submissions_review.csv');
        res.send(csv);
    });
});

// Start server
app.listen(PORT, () => {
    console.log(`Server running on port ${PORT}`);
});