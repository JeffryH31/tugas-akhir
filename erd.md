erDiagram
    users {
        bigint id PK
        string name
        string email UK
        string password
        decimal hourly_rate
        timestamp email_verified_at
        timestamp last_notifications_read_at
        string profile_photo_path
        string remember_token
        timestamp created_at
        timestamp updated_at
    }

    password_reset_tokens {
        string email PK
        string token
        timestamp created_at
    }

    sessions {
        string id PK
        bigint user_id FK
        string ip_address
        text user_agent
        longtext payload
        int last_activity
    }

    personal_access_tokens {
        bigint id PK
        string tokenable_type
        bigint tokenable_id
        string name
        string token UK
        text abilities
        timestamp last_used_at
        timestamp expires_at
        timestamp created_at
        timestamp updated_at
    }

    workspaces {
        bigint id PK
        string name
        string slug UK
        string color
        boolean is_personal
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    workspace_members {
        bigint id PK
        bigint workspace_id FK
        bigint user_id FK
        enum role
        timestamp created_at
        timestamp updated_at
    }

    spaces {
        bigint id PK
        bigint workspace_id FK
        bigint created_by FK
        string name
        string slug
        string color
        int position
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    space_members {
        bigint id PK
        bigint space_id FK
        bigint user_id FK
        enum role
        timestamp created_at
        timestamp updated_at
    }

    starred_spaces {
        bigint id PK
        bigint user_id FK
        bigint space_id FK
        bigint workspace_id FK
        timestamp starred_at
        timestamp created_at
        timestamp updated_at
    }

    folders {
        bigint id PK
        bigint space_id FK
        bigint parent_id FK
        bigint created_by FK
        string name
        string slug
        text description
        string color
        boolean is_hidden
        int position
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    statuses {
        bigint id PK
        bigint space_id FK
        string name
        string slug
        string color
        string type
        enum applies_to
        int position
        boolean is_default
        boolean is_closed
        timestamp created_at
        timestamp updated_at
    }

    task_lists {
        bigint id PK
        bigint space_id FK
        bigint folder_id FK
        bigint status_id FK
        bigint created_by FK
        string name
        string slug
        text description
        string color
        string icon
        boolean is_archived
        int position
        json settings
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    task_list_members {
        bigint id PK
        bigint task_list_id FK
        bigint user_id FK
        enum role
        timestamp created_at
        timestamp updated_at
    }

    labels {
        bigint id PK
        bigint workspace_id FK
        bigint space_id FK
        string name
        string color
        text description
        timestamp created_at
        timestamp updated_at
    }

    tasks {
        bigint id PK
        string task_id UK
        bigint task_list_id FK
        bigint status_id FK
        bigint created_by FK
        tinyint priority_level
        string name
        text description
        date start_date
        date due_date
        int time_estimate
        int position
        boolean is_archived
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    task_assignees {
        bigint id PK
        bigint task_id FK
        bigint user_id FK
        bigint assigned_by FK
    }

    task_labels {
        bigint id PK
        bigint task_id FK
        bigint label_id FK
        timestamp created_at
        timestamp updated_at
    }

    task_dependencies {
        bigint id PK
        bigint task_id FK
        bigint depends_on_task_id FK
        enum dependency_type
        timestamp created_at
        timestamp updated_at
    }

    comments {
        bigint id PK
        bigint task_id FK
        bigint subtask_id FK
        bigint user_id FK
        bigint parent_id FK
        text content
        json mentions
        json attachments
        boolean is_resolved
        timestamp edited_at
        timestamp created_at
        timestamp updated_at
    }

    activities {
        bigint id PK
        bigint workspace_id FK
        bigint user_id FK
        string subject_type
        bigint subject_id
        string action
        json properties
        json changes
        timestamp created_at
        timestamp updated_at
    }

    attachments {
        bigint id PK
        bigint task_id FK
        bigint user_id FK
        string name
        string original_name
        string path
        string disk
        string mime_type
        bigint size
        timestamp created_at
        timestamp updated_at
    }

    views {
        bigint id PK
        bigint task_list_id FK
        bigint space_id FK
        bigint user_id FK
        string name
        string type
        json filters
        json sorts
        json columns
        json settings
        boolean is_default
        boolean is_private
        int position
        timestamp created_at
        timestamp updated_at
    }

    sprints {
        bigint id PK
        bigint space_id FK
        bigint task_list_id FK
        string name
        text goal
        date start_date
        date end_date
        boolean is_active
        int position
        timestamp created_at
        timestamp updated_at
    }

    subtasks {
        bigint id PK
        string subtask_id UK
        bigint task_id FK
        bigint sprint_id FK
        bigint status_id FK
        bigint created_by FK
        bigint completed_by FK
        tinyint priority_level
        string name
        text description
        timestamp start_date
        timestamp due_date
        timestamp baseline_start_date
        timestamp baseline_due_date
        timestamp completed_at
        int time_estimate
        int optimistic_estimate
        int most_likely_estimate
        int pessimistic_estimate
        int time_spent
        tinyint progress
        int position
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    subtask_assignees {
        bigint id PK
        bigint subtask_id FK
        bigint user_id FK
        bigint assigned_by FK
    }

    subtask_labels {
        bigint id PK
        bigint subtask_id FK
        bigint label_id FK
        timestamp created_at
        timestamp updated_at
    }

    subtask_dependencies {
        bigint id PK
        bigint subtask_id FK
        bigint depends_on_subtask_id FK
        enum dependency_type
        timestamp created_at
        timestamp updated_at
    }

    time_entries {
        bigint id PK
        bigint subtask_id FK
        bigint user_id FK
        int duration
        timestamp started_at
        timestamp ended_at
        boolean is_billable
        boolean is_running
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    %% ─── Auth & Session ───
    users ||--o{ sessions : "has"

    %% ─── Workspace ───
    users ||--o{ workspace_members : "joins"
    workspaces ||--o{ workspace_members : "has"
    workspaces ||--o{ spaces : "contains"
    workspaces ||--o{ labels : "owns"
    workspaces ||--o{ activities : "logs"

    %% ─── Space ───
    spaces ||--o{ space_members : "has"
    spaces ||--o{ starred_spaces : "starred by"
    spaces ||--o{ folders : "has"
    spaces ||--o{ statuses : "defines"
    spaces ||--o{ task_lists : "has"
    spaces ||--o{ labels : "has"
    spaces ||--o{ views : "has"
    spaces ||--o{ sprints : "has"
    users ||--o{ space_members : "joins"
    users ||--o{ starred_spaces : "stars"

    %% ─── Folder (self-ref) ───
    folders ||--o{ folders : "parent of"
    folders ||--o{ task_lists : "groups"
    users ||--o{ spaces : "creates"
    users ||--o{ folders : "creates"

    %% ─── Task List ───
    task_lists ||--o{ task_list_members : "has"
    task_lists ||--o{ tasks : "contains"
    task_lists ||--o{ views : "has"
    task_lists ||--o{ sprints : "associated with"
    statuses ||--o{ task_lists : "default for"
    users ||--o{ task_list_members : "joins"
    users ||--o{ task_lists : "creates"

    %% ─── Task ───
    task_lists ||--o{ tasks : "has"
    statuses ||--o{ tasks : "applied to"
    tasks ||--o{ task_assignees : "assigned to"
    tasks ||--o{ task_labels : "tagged with"
    tasks ||--o{ task_dependencies : "depends on"
    tasks ||--o{ task_dependencies : "depended on by"
    tasks ||--o{ comments : "has"
    tasks ||--o{ attachments : "has"
    tasks ||--o{ subtasks : "has"
    users ||--o{ task_assignees : "assigned"
    users ||--o{ tasks : "creates"
    labels ||--o{ task_labels : "used in"

    %% ─── Subtask ───
    subtasks ||--o{ subtask_assignees : "assigned to"
    subtasks ||--o{ subtask_labels : "tagged with"
    subtasks ||--o{ subtask_dependencies : "depends on"
    subtasks ||--o{ subtask_dependencies : "depended on by"
    subtasks ||--o{ comments : "has"
    subtasks ||--o{ time_entries : "tracked by"
    statuses ||--o{ subtasks : "applied to"
    sprints ||--o{ subtasks : "contains"
    users ||--o{ subtask_assignees : "assigned"
    users ||--o{ subtasks : "creates"
    users ||--o{ subtasks : "completes"
    labels ||--o{ subtask_labels : "used in"

    %% ─── Comments (self-ref) ───
    comments ||--o{ comments : "replied by"
    users ||--o{ comments : "writes"

    %% ─── Activity ───
    users ||--o{ activities : "performs"

    %% ─── Attachment ───
    users ||--o{ attachments : "uploads"

    %% ─── Views ───
    users ||--o{ views : "owns"

    %% ─── Time Entries ───
    users ||--o{ time_entries : "logs"
