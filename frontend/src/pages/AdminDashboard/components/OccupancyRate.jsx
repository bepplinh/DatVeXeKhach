import React from "react";
import { PieChart, Pie, Cell, ResponsiveContainer, Label } from "recharts";

const OccupancyRate = ({ occupancyData = 0 }) => { // Default to 0 if no data
    const data = [
        { name: "Filled", value: occupancyData },
        { name: "Empty", value: 100 - occupancyData }
    ];

    const COLORS = ["#10b981", "#e5e7eb"]; // Green and Light Gray

    return (
        <div className="admin-dashboard__card h-full flex flex-col">
            <div className="admin-dashboard__card-header pb-2">
                <h2 className="admin-dashboard__card-title">Tỷ lệ lấp đầy</h2>
                <div className="text-xs text-gray-500">Trung bình hôm nay</div>
            </div>
            <div className="admin-dashboard__card-body flex items-center justify-around pt-2 px-4 pb-4 flex-1">
                <div className="w-32 h-32 relative flex-shrink-0">
                    <ResponsiveContainer width="100%" height="100%">
                        <PieChart>
                            <Pie
                                data={data}
                                cx="50%"
                                cy="50%"
                                innerRadius={35}
                                outerRadius={50}
                                fill="#8884d8"
                                paddingAngle={5}
                                dataKey="value"
                                startAngle={90}
                                endAngle={-270}
                            >
                                {data.map((entry, index) => (
                                    <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} stroke="none" />
                                ))}
                                <Label
                                    value={`${occupancyData}%`}
                                    position="center"
                                    className="text-lg font-bold fill-gray-900"
                                    style={{ fontSize: '18px', fontWeight: 'bold', fill: '#111827' }}
                                />
                            </Pie>
                        </PieChart>
                    </ResponsiveContainer>
                </div>
                <div className="flex flex-col gap-3 min-w-[100px]">
                    <div className="flex items-center gap-2 text-sm">
                        <div className="w-3 h-3 rounded-full bg-green-500"></div>
                        <span className="text-gray-600">Đã bán: <span className="font-semibold text-gray-900">{occupancyData}%</span></span>
                    </div>
                    <div className="flex items-center gap-2 text-sm">
                        <div className="w-3 h-3 rounded-full bg-gray-200"></div>
                        <span className="text-gray-600">Còn trống: <span className="font-semibold text-gray-900">{100 - occupancyData}%</span></span>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default OccupancyRate;
