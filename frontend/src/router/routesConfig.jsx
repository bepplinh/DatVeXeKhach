import { lazy } from "react";
import { CheckoutProvider } from "../contexts/CheckoutProvider";

const HomePage = lazy(() => import("../pages/HomePage/HomePage"));
const Login = lazy(() => import("../pages/Login/Login"));
const Register = lazy(() => import("../pages/Register/Register"));
const ForgotPassword = lazy(() => import("../pages/ForgotPassword/ForgotPassword"));
const Profile = lazy(() => import("../pages/Profile/Profile"));
const UserNotifications = lazy(() => import("../pages/UserNotifications/UserNotifications"));
const TicketManagement = lazy(() => import("../pages/TicketManagement/TicketManagement"));
const Trip = lazy(() => import("../pages/Trip/Trip"));
const CheckoutPage = lazy(() => import("../pages/Checkout/CheckoutPage"));
const BookSeat = lazy(() => import("../components/BookSeat/BookSeat"));
const AdminDashboard = lazy(() => import("../pages/AdminDashboard/AdminDashboard"));
const User = lazy(() => import("../pages/AdminDashboard/User/User"));
const Location = lazy(() => import("../pages/AdminDashboard/Location/Location"));
const AdminRoutePage = lazy(() => import("../pages/AdminDashboard/Route/Route"));
const SeatLayoutBuilder = lazy(() => import("../pages/AdminDashboard/SeatLayoutBuilder/SeatLayoutBuilder"));
const RouteOptimizationPage = lazy(() => import("../pages/AdminDashboard/RouteOptimization/RouteOptimization"));
const Notifications = lazy(() => import("../pages/AdminDashboard/Notifications/Notifications"));
const BusType = lazy(() => import("../pages/AdminDashboard/BusType/BusType"));
const BusManagement = lazy(() => import("../pages/AdminDashboard/BusManagement/BusManagement"));
const TripManagement = lazy(() => import("../pages/AdminDashboard/TripManagement/TripManagement"));
const TripRouteDetail = lazy(() => import("../pages/AdminDashboard/TripManagement/TripRouteDetail"));
const TripStationManagement = lazy(() => import("../pages/AdminDashboard/TripStation/TripStationManagement"));
const SupportChat = lazy(() => import("../pages/AdminDashboard/SupportChat/SupportChat"));
const CouponManagement = lazy(() => import("../pages/AdminDashboard/CouponManagement/CouponManagement"));
const RatingManagement = lazy(() => import("../pages/AdminDashboard/RatingManagement/RatingManagement"));
const PaymentManagement = lazy(() => import("../pages/AdminDashboard/PaymentManagement/PaymentManagement"));
const BookingSeatManagement = lazy(() => import("../pages/AdminDashboard/BookingSeatManagement/BookingSeatManagement"));
// TripPerformance removed
const RevenueDashboard = lazy(() => import("../pages/AdminDashboard/RevenueDashboard/RevenueDashboard"));
const RevenueAnalysis = lazy(() => import("../pages/AdminDashboard/RevenueAnalysis/RevenueAnalysis"));
const ScheduleTemplate = lazy(() => import("../pages/AdminDashboard/ScheduleTemplate/ScheduleTemplate"));


export const CLIENT_ROUTES = [
    { path: "/", element: <HomePage /> },
    { path: "/login", element: <Login /> },
    { path: "/register", element: <Register /> },
    { path: "/forgot-password", element: <ForgotPassword /> },
    { path: "/profile", element: <Profile /> },
    { path: "/profile/notifications", element: <UserNotifications /> },
    { path: "/tickets", element: <TicketManagement /> },
    { path: "/trip", element: <Trip /> },
    {
        path: "/checkout",
        element: (
            <CheckoutProvider>
                <CheckoutPage />
            </CheckoutProvider>
        ),
    },
    { path: "/book", element: <BookSeat /> },

];

export const ADMIN_ROUTES = [
    { path: "/admin", element: <AdminDashboard /> },
    { path: "/admin/users", element: <User /> },
    { path: "/admin/locations", element: <Location /> },
    { path: "/admin/routes", element: <AdminRoutePage /> },
    { path: "/admin/buses", element: <BusManagement /> },
    { path: "/admin/bus-types", element: <BusType /> },
    { path: "/admin/trips", element: <TripManagement /> },
    { path: "/admin/trips/:date/:routeId", element: <TripRouteDetail /> },
    { path: "/admin/trip-stations", element: <TripStationManagement /> },
    { path: "/admin/seat-layout", element: <SeatLayoutBuilder /> },
    { path: "/admin/route-optimization", element: <RouteOptimizationPage /> },
    { path: "/admin/notifications", element: <Notifications /> },
    { path: "/admin/support-chat", element: <SupportChat /> },
    { path: "/admin/coupons", element: <CouponManagement /> },
    { path: "/admin/ratings", element: <RatingManagement /> },
    { path: "/admin/payments", element: <PaymentManagement /> },
    { path: "/admin/booking-seat", element: <BookingSeatManagement /> },
    { path: "/admin/schedule-templates", element: <ScheduleTemplate /> },

    // TripPerformance route removed
    { path: "/admin/revenue", element: <RevenueDashboard /> },
    { path: "/admin/revenue/analysis", element: <RevenueAnalysis /> },
];


