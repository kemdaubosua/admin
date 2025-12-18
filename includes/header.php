<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Admin Panel'; ?> - AdminCenter</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root { --sidebar-width: 280px; }
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #f8fafc; 
            overflow-x: hidden; 
        }
        
        /* Table Style */
        .table-custom th { 
            font-size: 11px; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
            color: #9ca3af; 
            font-weight: 700; 
            background-color: #f9fafb; 
            border-bottom: 1px solid #e5e7eb; 
            padding: 16px; 
        }
        .table-custom td { 
            padding: 16px; 
            vertical-align: middle; 
            border-bottom: 1px solid #f3f4f6; 
        }
        
        /* Form Inputs */
        .form-control, .form-select { 
            padding: 0.75rem 1rem; 
            border-radius: 0.75rem; 
            border-color: #e5e7eb; 
            font-size: 0.875rem; 
        }
        .form-control:focus, .form-select:focus { 
            border-color: #3b82f6; 
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); 
        }
        
        /* Buttons */
        .btn-dark-custom { 
            background-color: #0f172a; 
            color: white; 
            border-radius: 0.75rem; 
            padding: 10px 24px; 
            font-weight: 600; 
            border: none; 
            font-size: 0.875rem; 
            transition: all 0.2s ease; 
        }
        .btn-dark-custom:hover { 
            background-color: #1e293b; 
            color: white; 
            transform: translateY(-1px); 
        }
        .btn-white { 
            background-color: white; 
            border: 1px solid #e2e8f0; 
            color: #475569; 
        }
        .btn-white:hover { 
            background-color: #f8fafc; 
            border-color: #cbd5e1; 
        }
        
        /* Layout */
        .main-content { 
            margin-left: var(--sidebar-width); 
            width: calc(100% - var(--sidebar-width)); 
            min-height: 100vh; 
            background-color: #f8fafc; 
            transition: margin 0.3s; 
        }
        
        /* Card */
        .card-custom {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .card-custom:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        
        /* Responsive */
        @media (max-width: 991.98px) {
            .main-content { 
                margin-left: 0; 
                width: 100%; 
                padding: 1rem !important; 
            }
            .table-custom th, .table-custom td { 
                padding: 12px 8px; 
                font-size: 0.875rem; 
            }
        }
    </style>
</head>
<body>