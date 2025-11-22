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
  created_at: string | null
  updated_at: string | null
  api_token?: string | null
  // relations
  cars: Car[]
  branches: Branch[]
  company: Company
  roles: Role[]
  report_months: ReportMonth[]
  tokens: PersonalAccessToken[]
  notifications: DatabaseNotification[]
  // counts
  cars_count: number
  branches_count: number
  roles_count: number
  report_months_count: number
  tokens_count: number
  notifications_count: number
  // exists
  cars_exists: boolean
  branches_exists: boolean
  company_exists: boolean
  roles_exists: boolean
  report_months_exists: boolean
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

export interface Visit {
  // columns
  id: number
  date: string | null
  examination: string | null
  statement: string | null
  patient_id: number | null
  month_id: number | null
  created_at: string | null
  updated_at: string | null
  // relations
  patient: Patient
  month: ReportMonth
  texts: TextBlock[]
  // counts
  texts_count: number
  // exists
  patient_exists: boolean
  month_exists: boolean
  texts_exists: boolean
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
  created_at: string | null
  updated_at: string | null
  // relations
  doctor: Doctor
  visits: Visit[]
  insurance_company: InsuranceCompany
  // counts
  visits_count: number
  // exists
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
  // relations
  company: Company
  users: User[]
  report_months: ReportMonth[]
  cars: Car[]
  // counts
  users_count: number
  report_months_count: number
  cars_count: number
  // exists
  company_exists: boolean
  users_exists: boolean
  report_months_exists: boolean
  cars_exists: boolean
}

export interface Car {
  // columns
  id: number
  evc: string | null
  company_id: number | null
  user_id: number | null
  created_at: string | null
  updated_at: string | null
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
  // relations
  branches: Branch[]
  cars: Car[]
  users: User[]
  // counts
  branches_count: number
  cars_count: number
  users_count: number
  // exists
  branches_exists: boolean
  cars_exists: boolean
  users_exists: boolean
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
