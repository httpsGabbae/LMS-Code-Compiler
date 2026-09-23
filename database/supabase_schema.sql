-- database/supabase_schema.sql : copy-paste into Supabase Dashboard → SQL Editor → New query → Run.
-- Postgres translation of database/schema.sql (snapshots, test_cases, submissions).
-- Safe to re-run: uses IF NOT EXISTS + DROP POLICY/TRIGGER guards.

-- 1. Tables ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.snapshots (
  id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  student_name VARCHAR(100) NOT NULL,
  class_code VARCHAR(50) NOT NULL,
  filename VARCHAR(255) NOT NULL DEFAULT '',
  language VARCHAR(20) NOT NULL,
  code TEXT NOT NULL,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT uq_snap_file UNIQUE (student_name, class_code, filename)
);
CREATE INDEX IF NOT EXISTS idx_snap_class ON public.snapshots (class_code, updated_at);

CREATE TABLE IF NOT EXISTS public.test_cases (
  id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  class_code VARCHAR(50) NOT NULL,
  language VARCHAR(20) NOT NULL,
  stdin TEXT NOT NULL,
  expected_stdout TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_tc ON public.test_cases (class_code, language);

CREATE TABLE IF NOT EXISTS public.submissions (
  id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  student_name VARCHAR(100) NOT NULL,
  class_code VARCHAR(50) NOT NULL,
  filename VARCHAR(255) NOT NULL DEFAULT '',
  language VARCHAR(20) NOT NULL,
  code TEXT NOT NULL,
  output TEXT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_sub ON public.submissions (class_code, created_at);

-- 2. Auto-bump updated_at (MySQL did ON UPDATE CURRENT_TIMESTAMP; Postgres needs a trigger)
CREATE OR REPLACE FUNCTION public.touch_updated_at()
RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
  NEW.updated_at = now();
  RETURN NEW;
END;
$$;
DROP TRIGGER IF EXISTS trg_snapshots_touch ON public.snapshots;
CREATE TRIGGER trg_snapshots_touch
BEFORE UPDATE ON public.snapshots
FOR EACH ROW EXECUTE FUNCTION public.touch_updated_at();

-- 3. Row Level Security (DEMO ONLY) ---------------------------------------
-- The publishable (anon) key can only touch tables with an RLS policy.
-- These policies allow full access — fine for LAN classroom demo behind your
-- firewall, UNSAFE on the open internet. Tighten before exposing publicly.
ALTER TABLE public.snapshots ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.test_cases ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.submissions ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "demo full access" ON public.snapshots;
CREATE POLICY "demo full access" ON public.snapshots
  FOR ALL TO anon USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS "demo full access" ON public.test_cases;
CREATE POLICY "demo full access" ON public.test_cases
  FOR ALL TO anon USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS "demo full access" ON public.submissions;
CREATE POLICY "demo full access" ON public.submissions
  FOR ALL TO anon USING (true) WITH CHECK (true);

-- 4. Verify ---------------------------------------------------------------
-- After Run, you should see 3 rows here:
-- SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename IN ('snapshots','test_cases','submissions');
