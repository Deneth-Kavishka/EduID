USE eduid;

INSERT INTO users (full_name, email, role, password_hash, status) VALUES
('System Admin', 'admin@eduid.local', 'admin', '$2y$10$5TUN0vxJ0sAcwGHtS5u5MOk1lA34qWOPF4MUPq7njLuHCHev.zZ2W', 'active'),
('Teacher One', 'teacher@eduid.local', 'teacher', '$2y$10$5TUN0vxJ0sAcwGHtS5u5MOk1lA34qWOPF4MUPq7njLuHCHev.zZ2W', 'active'),
('Student One', 'student@eduid.local', 'student', '$2y$10$5TUN0vxJ0sAcwGHtS5u5MOk1lA34qWOPF4MUPq7njLuHCHev.zZ2W', 'active'),
('Parent One', 'parent@eduid.local', 'parent', '$2y$10$5TUN0vxJ0sAcwGHtS5u5MOk1lA34qWOPF4MUPq7njLuHCHev.zZ2W', 'active');

INSERT INTO events (title, location, event_type, starts_at, ends_at) VALUES
('Mathematics Exam', 'Exam Hall A', 'exam', '2026-01-25 09:00:00', '2026-01-25 11:00:00'),
('Science Workshop', 'Event Hall C', 'event', '2026-01-26 14:00:00', '2026-01-26 16:30:00');

INSERT INTO access_logs (user_name, method, result, location) VALUES
('Student One', 'qr', 'passed', 'Exam Hall A'),
('Student One', 'face', 'passed', 'Exam Hall A');
