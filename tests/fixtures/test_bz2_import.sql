-- BZ2 Compression Test Fixture
-- This file is used for testing BZ2 import functionality

CREATE TABLE IF NOT EXISTS bz2_test (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    value INT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO bz2_test (name, value, description) VALUES ('test1', 100, 'First test record for BZ2 import');
INSERT INTO bz2_test (name, value, description) VALUES ('test2', 200, 'Second test record for BZ2 import');
INSERT INTO bz2_test (name, value, description) VALUES ('test3', 300, 'Third test record for BZ2 import');
INSERT INTO bz2_test (name, value, description) VALUES ('test4', 400, 'Fourth test record for BZ2 import');
INSERT INTO bz2_test (name, value, description) VALUES ('test5', 500, 'Fifth test record for BZ2 import');
INSERT INTO bz2_test (name, value, description) VALUES ('test6', 600, 'Sixth test record with special chars: \' " \\ ');
INSERT INTO bz2_test (name, value, description) VALUES ('test7', 700, 'Seventh test record');
INSERT INTO bz2_test (name, value, description) VALUES ('test8', 800, 'Eighth test record');
INSERT INTO bz2_test (name, value, description) VALUES ('test9', 900, 'Ninth test record');
INSERT INTO bz2_test (name, value, description) VALUES ('test10', 1000, 'Tenth and final test record');
