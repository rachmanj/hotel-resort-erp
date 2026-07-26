import { BellOutlined } from '@ant-design/icons';
import { Badge, Button } from 'antd';

export default function NotificationBell() {
    return (
        <Badge count={0} showZero={false}>
            <Button type="text" icon={<BellOutlined />} />
        </Badge>
    );
}
