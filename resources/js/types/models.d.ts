export interface User {
  // columns
  id: number
  first_name: string
  last_name: string
  title: string | null
  code: string | null
  phone_number: string | null
  email: string
  login: string
  pin?: string
  initials: string | null
  api_token?: string | null
  created_at: string | null
  updated_at: string | null
  // overrides
  role_names: string[]
  // relations
  cars: Car[]
  branches: Branch[]
  company: Company
  roles: Role[]
  report_months: ReportMonth[]
  patients: Patient[]
  represented_companies: Company[]
  tokens: PersonalAccessToken[]
  notifications: DatabaseNotification[]
  // counts
  cars_count: number
  branches_count: number
  roles_count: number
  report_months_count: number
  patients_count: number
  represented_companies_count: number
  tokens_count: number
  notifications_count: number
  // exists
  cars_exists: boolean
  branches_exists: boolean
  company_exists: boolean
  roles_exists: boolean
  report_months_exists: boolean
  patients_exists: boolean
  represented_companies_exists: boolean
  tokens_exists: boolean
  notifications_exists: boolean
}

export interface InsuranceCompany {
  // columns
  id: number
  name: string | null
  address: string | null
  city: string | null
  psc: string | null
  ico: string | null
  dic: string | null
  ic_dph: string | null
  register: string | null
  code: string | null
  branch_code: string | null
  created_at: string | null
  updated_at: string | null
  // relations
  patients: Patient[]
  branches: Branch[]
  // counts
  patients_count: number
  branches_count: number
  // exists
  patients_exists: boolean
  branches_exists: boolean
}

export interface VisitText {
  // columns
  visit_id: number
  text_id: number
  // relations
  visit: Visit
  text: TextBlock
  // counts
  // exists
  visit_exists: boolean
  text_exists: boolean
}

export interface Macro {
  // columns
  id: number
  name: string | null
  text: string | null
  abbreviation: string | null
  user_id: number | null
  created_at: string | null
  updated_at: string | null
  // relations
  user: User
  // counts
  // exists
  user_exists: boolean
}

export interface Document {
  // columns
  id: number
  patient_id: number | null
  user_id: number
  type: string
  mime_type: string
  name: string
  path: string
  created_at: string | null
  updated_at: string | null
  // relations
  patient: Patient
  user: User
  // counts
  // exists
  patient_exists: boolean
  user_exists: boolean
}

export interface Visit {
  // columns
  id: number
  date: string | null
  patient_id: number | null
  created_at: string | null
  updated_at: string | null
  user_id: number | null
  branch_id: number | null
  terrain_time: string | null
  administrative_time: string | null
  time_on_location: number | null
  distance_to_location: number | null
  time_to_location: number | null
  // relations
  patient: Patient
  user: User
  branch: Branch
  // counts
  // exists
  patient_exists: boolean
  user_exists: boolean
  branch_exists: boolean
}

export interface Procedure {
  // columns
  id: number
  code: string | null
  description: string | null
  created_at: string | null
  updated_at: string | null
  // relations
  insurance_companies_prices: InsuranceCompany[]
  insurance_companies_prices_minimal: InsuranceCompany[]
  // counts
  insurance_companies_prices_count: number
  insurance_companies_prices_minimal_count: number
  // exists
  insurance_companies_prices_exists: boolean
  insurance_companies_prices_minimal_exists: boolean
}

export interface NurseDiagnosis {
  // columns
  id: number
  code: string | null
  description: string | null
  created_at: string | null
  updated_at: string | null
}

export interface Doctor {
  // columns
  id: number
  first_name: string | null
  last_name: string | null
  title: string | null
  zpr: string | null
  pzs: string | null
  created_at: string | null
  updated_at: string | null
  // relations
  patients: Patient[]
  // counts
  patients_count: number
  // exists
  patients_exists: boolean
}

export interface Patient {
  // columns
  id: number
  first_name: string | null
  last_name: string | null
  title: string | null
  personal_number: string | null
  sex: string | null
  contact: string | null
  doctor_id: number | null
  insurance_company_id: number | null
  address: string | null
  city: string | null
  zip: string | null
  latitude: number | null
  longitude: number | null
  reference_date: string | null
  created_at: string | null
  updated_at: string | null
  deleted_at: string | null
  dekurz_number: string | null
  // relations
  assigned_users: User[]
  branches: Branch[]
  doctor: Doctor
  visits: Visit[]
  insurance_company: InsuranceCompany
  // counts
  assigned_users_count: number
  branches_count: number
  visits_count: number
  // exists
  assigned_users_exists: boolean
  branches_exists: boolean
  doctor_exists: boolean
  visits_exists: boolean
  insurance_company_exists: boolean
}

export interface Branch {
  // columns
  id: number
  company_id: number | null
  code: string | null
  identificator: string | null
  address: string | null
  city: string | null
  psc: string | null
  phone: string | null
  email: string | null
  latitude: number | null
  longitude: number | null
  created_at: string | null
  updated_at: string | null
  representative_id: number | null
  terrain_start_time: string | null
  administrative_start_time: string | null
  per_location_time: number | null
  // relations
  company: Company
  representative: User
  users: User[]
  report_months: ReportMonth[]
  cars: Car[]
  patients: Patient[]
  favourite_doctors: Doctor[]
  // counts
  users_count: number
  report_months_count: number
  cars_count: number
  patients_count: number
  favourite_doctors_count: number
  // exists
  company_exists: boolean
  representative_exists: boolean
  users_exists: boolean
  report_months_exists: boolean
  cars_exists: boolean
  patients_exists: boolean
  favourite_doctors_exists: boolean
}

export interface Car {
  // columns
  id: number
  evc: string | null
  company_id: number | null
  user_id: number | null
  created_at: string | null
  updated_at: string | null
  model: string | null
  // relations
  company: Company
  user: User
  // counts
  // exists
  company_exists: boolean
  user_exists: boolean
}

export interface Role {
  // columns
  id: number
  position: string | null
  created_at: string | null
  updated_at: string | null
  // relations
  users: User[]
  // counts
  users_count: number
  // exists
  users_exists: boolean
}

export interface PatientPoint {
  // columns
  id: number
  date: string | null
  patient_personal_number: string | null
  patient_name: string | null
  patient_id: number | null
  diagnosis_code: string | null
  diagnosis_id: number | null
  procedure_code: string | null
  procedure_id: number | null
  doctor_pzs: string | null
  doctor_zpr: string | null
  doctor_id: number | null
  reference_date: string | null
  user_id: number | null
  branch_id: number | null
  quantity: number | null
  created_at: string | null
  updated_at: string | null
}

export interface Company {
  // columns
  id: number
  name: string | null
  ico: string | null
  dic: string | null
  ic_dph: string | null
  iban: string | null
  bic: string | null
  register: string | null
  address: string | null
  city: string | null
  psc: string | null
  phone: string | null
  email: string | null
  latitude: number | null
  longitude: number | null
  created_at: string | null
  updated_at: string | null
  representative_id: number | null
  // relations
  branches: Branch[]
  cars: Car[]
  users: User[]
  representative: User
  // counts
  branches_count: number
  cars_count: number
  users_count: number
  // exists
  branches_exists: boolean
  cars_exists: boolean
  users_exists: boolean
  representative_exists: boolean
}

export interface TextBlock {
  // columns
  id: number
  text: string | null
  position: number | null
  created_at: string | null
  updated_at: string | null
  // relations
  visits: Visit[]
  // counts
  visits_count: number
  // exists
  visits_exists: boolean
}

export interface ReportMonth {
  // columns
  id: number
  month: number | null
  year: number | null
  examination_start: string | null
  examination_end: string | null
  statement_start: string | null
  statement_end: string | null
  first_day: string | null
  last_day: string | null
  user_id: number | null
  branch_id: number | null
  created_at: string | null
  updated_at: string | null
  // relations
  user: User
  branch: Branch
  visits: Visit[]
  // counts
  visits_count: number
  // exists
  user_exists: boolean
  branch_exists: boolean
  visits_exists: boolean
}

export interface Diagnosis {
  // columns
  id: number
  code: string | null
  description: string | null
  created_at: string | null
  updated_at: string | null
}
