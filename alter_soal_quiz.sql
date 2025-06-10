-- Add image_url column to soal_quiz table
ALTER TABLE soal_quiz
ADD COLUMN image_url VARCHAR(255) NULL AFTER pertanyaan;

-- Update existing queries to include the new field 