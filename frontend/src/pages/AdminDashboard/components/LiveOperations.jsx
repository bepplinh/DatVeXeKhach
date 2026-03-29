import React, { useState, useEffect } from "react";
import { Clock, MapPin, Bus, User } from "lucide-react";
import apiClient from "../../../apis/axiosClient";
import { formatRelativeTime } from "../utils";

const LiveOperations = ({ trips = [], loading = false }) => {
    // Component logic now simplified, no internal fetch
    // Use trips prop for rendering


    return (
        <div className="admin-dashboard__card admin-dashboard__card--full">
            <div className="admin-dashboard__card-header">
                <h2 className="admin-dashboard__card-title">
                    <Clock size={20} className="inline-block mr-2 text-blue-500" />
                    Chuyến xe sắp khởi hành
                </h2>
                <div className="text-sm text-gray-500">
                    Cập nhật mỗi phút
                </div>
            </div>
            <div className="admin-dashboard__card-body">
                {loading ? (
                    <div className="text-center py-4 text-gray-500">Đang tải dữ liệu vận hành...</div>
                ) : trips.length > 0 ? (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="text-sm text-gray-500 border-b border-gray-100">
                                    <th className="py-2 font-medium">Tuyến đường</th>
                                    <th className="py-2 font-medium">Giờ chạy</th>
                                    <th className="py-2 font-medium">Xe & Tài xế</th>
                                    <th className="py-2 font-medium text-right">Khách</th>
                                    <th className="py-2 font-medium text-right">Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody>
                                {trips.map(trip => {
                                    const departure = new Date(trip.departure_time);
                                    const occupancy = trip.bookings_count || 0; // Assuming this field exists or similar
                                    // Make sure to handle missing data gracefully
                                    const capacity = trip.bus?.type?.capacity || 40;
                                    const occupancyPercent = (occupancy / capacity) * 100;

                                    return (
                                        <tr key={trip.id} className="border-b border-gray-50 last:border-0 hover:bg-gray-50 transition-colors">
                                            <td className="py-3">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium text-gray-900">
                                                        {trip.route?.from_city?.name} → {trip.route?.to_city?.name}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="py-3">
                                                <div className="text-sm">
                                                    <span className="font-bold text-blue-600">
                                                        {departure.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })}
                                                    </span>
                                                    <div className="text-xs text-gray-500">
                                                        {formatRelativeTime(trip.departure_time)}
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="py-3">
                                                <div className="flex flex-col text-sm">
                                                    <div className="flex items-center gap-1">
                                                        <Bus size={14} className="text-gray-400" />
                                                        <span>{trip.bus?.license_plate || "Chưa xếp xe"}</span>
                                                    </div>
                                                    <div className="flex items-center gap-1 text-gray-500">
                                                        <User size={14} />
                                                        <span>{trip.driver?.full_name || "Chưa xếp tài"}</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="py-3 text-right">
                                                <div className="flex flex-col items-end">
                                                    <span className="font-medium">{occupancy}/{capacity}</span>
                                                    <div className="w-16 h-1.5 bg-gray-200 rounded-full mt-1 overflow-hidden">
                                                        <div
                                                            className={`h-full rounded-full ${occupancyPercent > 80 ? 'bg-red-500' : occupancyPercent > 50 ? 'bg-green-500' : 'bg-blue-500'}`}
                                                            style={{ width: `${occupancyPercent}%` }}
                                                        />
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="py-3 text-right">
                                                <span className="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                                    Sẵn sàng
                                                </span>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="text-center py-8 text-gray-400">
                        <Bus size={48} className="mx-auto mb-2 opacity-20" />
                        <p>Không có chuyến xe nào sắp khởi hành trong 6 giờ tới</p>
                    </div>
                )}
            </div>
        </div>
    );
};

export default LiveOperations;
