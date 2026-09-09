import moment from 'moment';

export const can = (permissions, user) =>
    (user?.perms || []).filter((el) => [...permissions, 'Administrator'].includes(el)).length ? true : false;

export const stringIsWeekend = (dateString) => {
    const dt = moment(dateString).isoWeekday();
    const ar = [6, 7];
    return ar.includes(dt);
};
