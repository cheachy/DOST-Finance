// import { Link, usePage } from "@inertiajs/react";
// import {
//   LayoutGrid,
//   BookText,
//   Upload,
//   FileBarChart,
//   Wallet,
//   Settings,
//   LogOut,
//   ChevronsUpDown,
// } from "lucide-react";
// import { useState } from "react";

// const navItems = [
//   { label: "Dashboard", href: "dashboard", icon: LayoutGrid },
//   { label: "Ledgers", href: "ledgers.index", icon: BookText },
//   { label: "Import", href: "imports.create", icon: Upload },
//   { label: "Fund Receipts", href: "fund-receipts.index", icon: Wallet },
//   { label: "Reports", href: "reports.index", icon: FileBarChart },
// ];

// export default function AppLayout({ children, breadcrumb }) {
//   const { auth } = usePage().props;
//   const user = auth?.user ?? { name: "User", role: "staff" };
//   const [menuOpen, setMenuOpen] = useState(false);

//   const initials = user.name
//     .split(" ")
//     .map((n) => n[0])
//     .slice(0, 2)
//     .join("")
//     .toUpperCase();

//   return (
//     <div className="app-shell">
//       <aside className="app-sidebar">
//         <div className="app-sidebar-brand">
//           <div className="app-seal">
//             <span>T</span>
//           </div>
//           <div>
//             <p className="app-brand-name">Talaan</p>
//             <p className="app-brand-tagline">Ledger &amp; disbursements</p>
//           </div>
//         </div>

//         <nav className="app-nav">
//           {navItems.map(({ label, href, icon: Icon }) => (
//             <Link key={href} href={route(href)} className="app-nav-link">
//               <Icon size={17} strokeWidth={1.75} />
//               {label}
//             </Link>
//           ))}
//         </nav>

//         <div className="app-user-menu">
//           <button className="app-user-button" onClick={() => setMenuOpen((v) => !v)}>
//             <div className="app-user-avatar">{initials}</div>
//             <div className="app-user-info">
//               <p className="app-user-name">{user.name}</p>
//               <p className="app-user-role">{user.role}</p>
//             </div>
//             <ChevronsUpDown size={14} className="app-user-chevron" />
//           </button>

//           {menuOpen && (
//             <div className="app-user-dropdown">
//               <Link href={route("profile.edit")} className="app-dropdown-item">
//                 <Settings size={15} strokeWidth={1.75} />
//                 Settings
//               </Link>
//               <Link
//                 href={route("logout")}
//                 method="post"
//                 as="button"
//                 className="app-dropdown-item app-dropdown-item--danger"
//               >
//                 <LogOut size={15} strokeWidth={1.75} />
//                 Sign out
//               </Link>
//             </div>
//           )}
//         </div>
//       </aside>

//       <div className="app-main-column">
//         <header className="app-header">
//           <p className="app-breadcrumb">{breadcrumb ?? "Dashboard"}</p>
//         </header>

//         <main className="app-content">{children}</main>
//       </div>
//     </div>
//   );
// }